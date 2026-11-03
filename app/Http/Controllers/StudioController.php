<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\StudioGeneration;
use App\Models\StudioReference;
use App\Services\GeminiImageClient;
use App\Services\GeminiImageException;
use App\Support\GeminiCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Image generation for the team: pick references, describe the image, generate, refine.
 */
class StudioController extends Controller
{
    private const PRODUCTS = [
        'cleanse' => 'Pr1me Cleanse',
        'coffee' => 'Pr1me C 20in1 Coffee',
        'ginger' => 'Pr1me Ginger Turmeric',
        'lumine' => 'Pr1me Lumine',
        'scrub' => 'Pr1me Skin Nia Placenta Scrub',
        'soap' => 'Pr1me Skin Ultimate 4 in 1 Soap',
    ];

    private const ASPECT_RATIOS = [
        '1:1' => 'Square 1:1 (feed post)',
        '4:5' => 'Portrait 4:5 (feed post)',
        '3:4' => 'Portrait 3:4',
        '9:16' => 'Vertical 9:16 (story, reel)',
        '16:9' => 'Wide 16:9 (cover, video)',
        '4:3' => 'Landscape 4:3',
    ];

    /** Google's paid price per image for gemini-nano-banana-2.1, as a guide on the page. */
    private const IMAGE_SIZES = [
        '1K' => ['label' => 'Standard (1K)', 'cost' => 'about $0.03'],
        '2K' => ['label' => 'High (2K)', 'cost' => 'about $0.05'],
        '4K' => ['label' => 'Maximum (4K)', 'cost' => 'about $0.11'],
    ];

    private const REFERENCE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MAX_REFERENCES = 6;

    private const MAX_REFERENCE_BYTES = 15 * 1024 * 1024;

    public function index(Request $request): Response
    {
        $this->authorizeAction($request, 'view');
        $brandIds = $this->brandIds($request);

        $generations = StudioGeneration::query()
            ->whereIn('brand_id', $brandIds)
            ->with('user:id,name')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $selected = $request->filled('image')
            ? StudioGeneration::query()->whereIn('brand_id', $brandIds)->with('user:id,name')->find($request->integer('image'))
            : null;

        $uploads = StudioReference::query()->whereIn('brand_id', $brandIds)->latest('id')->limit(60)->get();
        $user = $request->user();

        return Inertia::render('Studio/Index', [
            'connection' => [
                'connected' => GeminiCredentials::isConnected(),
                'hint' => GeminiCredentials::hint(),
                'connected_by' => GeminiCredentials::connectedBy(),
            ],
            'products' => [
                ...collect(self::PRODUCTS)->map(fn (string $name, string $key) => [
                    'key' => 'product:'.$key,
                    'name' => $name,
                    'url' => route('studio.products.image', $key),
                    'id' => null,
                ])->values(),
                ...$uploads->where('kind', 'product')->map(fn (StudioReference $item) => $this->presentReference($item))->values(),
            ],
            'references' => $uploads->where('kind', 'reference')->map(fn (StudioReference $item) => $this->presentReference($item))->values(),
            'generations' => [
                'data' => $generations->getCollection()->map(fn (StudioGeneration $item) => $this->presentGeneration($item))->values(),
                'prev' => $generations->previousPageUrl(),
                'next' => $generations->nextPageUrl(),
                'from' => $generations->firstItem(),
                'to' => $generations->lastItem(),
                'total' => $generations->total(),
            ],
            'selected' => $selected ? $this->presentGeneration($selected) : null,
            'aspectRatios' => collect(self::ASPECT_RATIOS)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'imageSizes' => collect(self::IMAGE_SIZES)->map(fn (array $size, string $value) => ['value' => $value, ...$size])->values(),
            'maxReferences' => self::MAX_REFERENCES,
            'canGenerate' => $user->hasPermission('studio.create'),
            'canManage' => $user->hasPermission('studio.update'),
            'canDelete' => $user->hasPermission('studio.delete'),
        ]);
    }

    public function connect(Request $request, GeminiImageClient $gemini): RedirectResponse
    {
        $this->authorizeAction($request, 'update');
        $data = $request->validate(['api_key' => ['required', 'string', 'min:20', 'max:200']]);
        $key = trim($data['api_key']);

        try {
            $gemini->verifyKey($key);
        } catch (GeminiImageException $exception) {
            throw ValidationException::withMessages(['api_key' => $exception->getMessage()]);
        }

        GeminiCredentials::save($key, $request->user()->name);

        return back()->with('success', 'Google is connected. You can generate images now.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->authorizeAction($request, 'update');
        GeminiCredentials::forget();

        return back()->with('success', 'Google is disconnected.');
    }

    public function generate(Request $request, GeminiImageClient $gemini): RedirectResponse
    {
        $this->authorizeAction($request, 'create');

        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'aspect_ratio' => ['required', Rule::in(array_keys(self::ASPECT_RATIOS))],
            'image_size' => ['required', Rule::in(array_keys(self::IMAGE_SIZES))],
            'references' => ['nullable', 'array', 'max:'.self::MAX_REFERENCES],
            'references.*' => ['string', 'max:40'],
            'parent_id' => ['nullable', 'integer'],
        ], [
            'prompt.required' => 'Describe what you want in the image.',
            'references.max' => 'Choose up to '.self::MAX_REFERENCES.' reference images.',
        ]);

        $key = GeminiCredentials::key();
        if ($key === '') {
            throw ValidationException::withMessages(['generate' => 'Connect Google before generating images.']);
        }

        $parent = isset($data['parent_id'])
            ? StudioGeneration::query()->whereIn('brand_id', $this->brandIds($request))->find($data['parent_id'])
            : null;
        abort_if(isset($data['parent_id']) && ! $parent, 404);

        $prompt = trim($data['prompt']);
        $keys = array_values(array_unique($data['references'] ?? []));
        $images = [];

        if ($parent) {
            // A change to an image starts from that image, with its references to keep products accurate.
            $disk = Storage::disk('local');
            if (! $disk->exists($parent->path)) {
                throw ValidationException::withMessages(['generate' => 'The image to change is no longer available.']);
            }
            $images[] = ['mime' => $parent->mime_type, 'bytes' => (string) $disk->get($parent->path)];
            $keys = array_values(array_slice($parent->reference_keys ?? [], 0, self::MAX_REFERENCES));
            $images = [...$images, ...$this->loadReferences($request, $keys, false)];
            $instruction = 'Edit the first image as described here, and keep everything else the same, especially product labels and logos: '.$prompt;
        } else {
            $images = $this->loadReferences($request, $keys, true);
            $instruction = $prompt;
        }

        if (array_sum(array_map(fn (array $image) => strlen($image['bytes']), $images)) > self::MAX_REFERENCE_BYTES) {
            throw ValidationException::withMessages(['generate' => 'These images are too large to send together. Choose fewer or smaller ones.']);
        }

        @set_time_limit(240);

        try {
            $result = $gemini->generate($key, $instruction, $images, $data['aspect_ratio'], $data['image_size']);
        } catch (GeminiImageException $exception) {
            throw ValidationException::withMessages(['generate' => $exception->getMessage()]);
        }

        $brandId = $parent?->brand_id ?? $this->defaultBrand($request)->id;
        $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$result['mime']];
        $path = 'studio/generated/'.$brandId.'/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($path, $result['bytes']);

        $generation = StudioGeneration::query()->create([
            'brand_id' => $brandId,
            'user_id' => $request->user()->id,
            'parent_id' => $parent?->id,
            'prompt' => $prompt,
            'aspect_ratio' => $data['aspect_ratio'],
            'image_size' => $data['image_size'],
            'model' => $gemini->model(),
            'reference_keys' => $keys,
            'path' => $path,
            'mime_type' => $result['mime'],
            'size' => strlen($result['bytes']),
            'width' => $result['width'],
            'height' => $result['height'],
        ]);

        return redirect()->route('studio.index', ['image' => $generation->id])->with('success', 'Image generated.');
    }

    public function storeReference(Request $request): RedirectResponse
    {
        $this->authorizeAction($request, 'create');

        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'mimetypes:'.implode(',', self::REFERENCE_MIMES), 'max:10240'],
            'kind' => ['required', Rule::in(StudioReference::KINDS)],
            'name' => ['nullable', 'string', 'max:150'],
        ], [
            'file.mimetypes' => 'Upload a JPG, PNG or WebP image.',
            'file.image' => 'Upload a JPG, PNG or WebP image.',
            'file.max' => 'Images can be up to 10 MB.',
        ]);

        $file = $request->file('file');
        $brandId = $this->defaultBrand($request)->id;
        $mime = (string) $file->getMimeType();
        $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime];
        $path = $file->storeAs('studio/references/'.$brandId, Str::uuid().'.'.$extension, ['disk' => 'local']);

        StudioReference::query()->create([
            'brand_id' => $brandId,
            'user_id' => $request->user()->id,
            'kind' => $data['kind'],
            'name' => trim($data['name'] ?? '') ?: Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 140, ''),
            'path' => $path,
            'mime_type' => $mime,
            'size' => (int) $file->getSize(),
        ]);

        return back()->with('success', $data['kind'] === 'product' ? 'Product image added.' : 'Reference uploaded.');
    }

    public function destroyReference(Request $request, StudioReference $reference): RedirectResponse
    {
        $this->authorizeBrand($request, $reference->brand_id);
        abort_unless($request->user()->hasPermission('studio.delete') || $reference->user_id === $request->user()->id, 403);

        Storage::disk('local')->delete($reference->path);
        $reference->delete();

        return back()->with('success', 'Image removed.');
    }

    public function referenceImage(Request $request, StudioReference $reference): StreamedResponse
    {
        $this->authorizeBrand($request, $reference->brand_id);

        return $this->inline($reference->path, $reference->mime_type);
    }

    public function productImage(Request $request, string $product): BinaryFileResponse
    {
        $this->authorizeAction($request, 'view');
        abort_unless(isset(self::PRODUCTS[$product]), 404);

        return response()->file($this->productPath($product), ['Cache-Control' => 'private, max-age=86400']);
    }

    public function image(Request $request, StudioGeneration $generation): StreamedResponse
    {
        $this->authorizeBrand($request, $generation->brand_id);

        return $this->inline($generation->path, $generation->mime_type);
    }

    public function download(Request $request, StudioGeneration $generation): StreamedResponse
    {
        $this->authorizeBrand($request, $generation->brand_id);
        abort_unless(Storage::disk('local')->exists($generation->path), 404);

        return Storage::disk('local')->download($generation->path, 'studio-'.$generation->id.'.'.pathinfo($generation->path, PATHINFO_EXTENSION));
    }

    public function destroy(Request $request, StudioGeneration $generation): RedirectResponse
    {
        $this->authorizeBrand($request, $generation->brand_id);
        abort_unless($request->user()->hasPermission('studio.delete') || $generation->user_id === $request->user()->id, 403);

        Storage::disk('local')->delete($generation->path);
        $generation->delete();

        return redirect()->route('studio.index')->with('success', 'Image deleted.');
    }

    /**
     * @param  list<string>  $keys
     * @return list<array{mime: string, bytes: string}>
     */
    private function loadReferences(Request $request, array $keys, bool $strict): array
    {
        $images = [];

        foreach ($keys as $key) {
            $image = $this->loadReference($request, $key);
            if ($image !== null) {
                $images[] = $image;
            } elseif ($strict) {
                throw ValidationException::withMessages(['references' => 'One of the chosen reference images is no longer available.']);
            }
        }

        return $images;
    }

    /**
     * @return array{mime: string, bytes: string}|null
     */
    private function loadReference(Request $request, string $key): ?array
    {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, '');

        if ($type === 'product' && isset(self::PRODUCTS[$id])) {
            return ['mime' => 'image/jpeg', 'bytes' => (string) file_get_contents($this->productPath($id))];
        }

        if ($type === 'upload' && ctype_digit($id)) {
            $reference = StudioReference::query()->whereIn('brand_id', $this->brandIds($request))->find((int) $id);
            if ($reference && Storage::disk('local')->exists($reference->path)) {
                return ['mime' => $reference->mime_type, 'bytes' => (string) Storage::disk('local')->get($reference->path)];
            }
        }

        return null;
    }

    private function productPath(string $product): string
    {
        return resource_path('studio-products/'.$product.'.jpg');
    }

    private function inline(string $path, string $mime): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentReference(StudioReference $reference): array
    {
        return [
            'key' => $reference->key(),
            'id' => $reference->id,
            'name' => $reference->name,
            'url' => route('studio.references.image', $reference),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentGeneration(StudioGeneration $generation): array
    {
        return [
            'id' => $generation->id,
            'parent_id' => $generation->parent_id,
            'prompt' => $generation->prompt,
            'aspect_ratio' => $generation->aspect_ratio,
            'image_size' => $generation->image_size,
            'references' => $generation->reference_keys ?? [],
            'url' => route('studio.images.show', $generation),
            'download_url' => route('studio.images.download', $generation),
            'width' => $generation->width,
            'height' => $generation->height,
            'user' => $generation->user?->name,
            'created_at' => $generation->created_at?->toIso8601String(),
        ];
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($request->user()->hasPermission('studio.'.$action), 403);
    }

    private function authorizeBrand(Request $request, int $brandId): void
    {
        $this->authorizeAction($request, 'view');
        abort_unless($request->user()->canAccessBrand($brandId), 403);
    }

    /**
     * @return list<int>
     */
    private function brandIds(Request $request): array
    {
        return $request->user()->accessibleBrandIds() ?: [0];
    }

    private function defaultBrand(Request $request): Brand
    {
        $brand = Brand::query()
            ->whereIn('id', $this->brandIds($request))
            ->where('is_active', true)
            ->orderByRaw("case when name = 'Prime Z' then 0 else 1 end")
            ->orderBy('name')
            ->first();

        abort_if($brand === null, 422, 'Add a brand before using Studio.');

        return $brand;
    }
}
