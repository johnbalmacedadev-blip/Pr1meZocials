<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\StudioGeneration;
use App\Models\StudioReference;
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
 * The team's workspace for Google Flow: choose references and write the prompt here, make
 * the image in Flow with the user's own Google account, then keep the result here.
 * Google offers no way for other apps to use Flow, so nothing here talks to Google.
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

    private const EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    private const MAX_REFERENCES = 6;

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
            'flowUrl' => config('services.google_flow.url'),
            'products' => [
                ...collect(self::PRODUCTS)->map(fn (string $name, string $key) => [
                    'key' => 'product:'.$key,
                    'id' => null,
                    'name' => $name,
                    'url' => route('studio.products.image', $key),
                    'filename' => $key.'.jpg',
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
            'maxReferences' => self::MAX_REFERENCES,
            'canCreate' => $user->hasPermission('studio.create'),
            'canDelete' => $user->hasPermission('studio.delete'),
        ]);
    }

    /**
     * Keeps an image made in Flow, with the prompt and references that went into it.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAction($request, 'create');

        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'mimetypes:'.implode(',', array_keys(self::EXTENSIONS)), 'max:25600'],
            'prompt' => ['required', 'string', 'max:4000'],
            'aspect_ratio' => ['required', Rule::in(array_keys(self::ASPECT_RATIOS))],
            'references' => ['nullable', 'array', 'max:'.self::MAX_REFERENCES],
            'references.*' => ['string', 'max:40'],
            'parent_id' => ['nullable', 'integer'],
        ], [
            'file.required' => 'Choose the image you made in Flow.',
            'file.image' => 'Upload a JPG, PNG or WebP image.',
            'file.mimetypes' => 'Upload a JPG, PNG or WebP image.',
            'file.max' => 'Images can be up to 25 MB.',
            'references.max' => 'Choose up to '.self::MAX_REFERENCES.' reference images.',
        ]);

        $keys = array_values(array_unique($data['references'] ?? []));
        foreach ($keys as $key) {
            if (! $this->referenceExists($request, $key)) {
                throw ValidationException::withMessages(['references' => 'One of the chosen reference images is no longer available.']);
            }
        }

        $parent = isset($data['parent_id'])
            ? StudioGeneration::query()->whereIn('brand_id', $this->brandIds($request))->find($data['parent_id'])
            : null;
        abort_if(isset($data['parent_id']) && ! $parent, 404);

        $file = $request->file('file');
        $brandId = $parent?->brand_id ?? $this->defaultBrand($request)->id;
        $mime = (string) $file->getMimeType();
        $path = $file->storeAs('studio/generated/'.$brandId, Str::uuid().'.'.self::EXTENSIONS[$mime], ['disk' => 'local']);
        $dimensions = @getimagesize($file->getRealPath()) ?: [null, null];

        $generation = StudioGeneration::query()->create([
            'brand_id' => $brandId,
            'user_id' => $request->user()->id,
            'parent_id' => $parent?->id,
            'prompt' => trim($data['prompt']),
            'aspect_ratio' => $data['aspect_ratio'],
            'reference_keys' => $keys,
            'path' => $path,
            'mime_type' => $mime,
            'size' => (int) $file->getSize(),
            'width' => $dimensions[0],
            'height' => $dimensions[1],
        ]);

        return redirect()->route('studio.index', ['image' => $generation->id])->with('success', 'Image saved to Studio.');
    }

    public function storeReference(Request $request): RedirectResponse
    {
        $this->authorizeAction($request, 'create');

        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'mimetypes:'.implode(',', array_keys(self::EXTENSIONS)), 'max:10240'],
            'kind' => ['required', Rule::in(StudioReference::KINDS)],
            'name' => ['nullable', 'string', 'max:150'],
        ], [
            'file.image' => 'Upload a JPG, PNG or WebP image.',
            'file.mimetypes' => 'Upload a JPG, PNG or WebP image.',
            'file.max' => 'Images can be up to 10 MB.',
        ]);

        $file = $request->file('file');
        $brandId = $this->defaultBrand($request)->id;
        $mime = (string) $file->getMimeType();
        $path = $file->storeAs('studio/references/'.$brandId, Str::uuid().'.'.self::EXTENSIONS[$mime], ['disk' => 'local']);

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

        return response()->file(resource_path('studio-products/'.$product.'.jpg'), ['Cache-Control' => 'private, max-age=86400']);
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

        return Storage::disk('local')->download($generation->path, $this->generationFilename($generation));
    }

    public function destroy(Request $request, StudioGeneration $generation): RedirectResponse
    {
        $this->authorizeBrand($request, $generation->brand_id);
        abort_unless($request->user()->hasPermission('studio.delete') || $generation->user_id === $request->user()->id, 403);

        Storage::disk('local')->delete($generation->path);
        $generation->delete();

        return redirect()->route('studio.index')->with('success', 'Image deleted.');
    }

    private function referenceExists(Request $request, string $key): bool
    {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, '');

        if ($type === 'product') {
            return isset(self::PRODUCTS[$id]);
        }

        return $type === 'upload'
            && ctype_digit($id)
            && StudioReference::query()->whereIn('brand_id', $this->brandIds($request))->whereKey((int) $id)->exists();
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

    private function generationFilename(StudioGeneration $generation): string
    {
        return 'studio-'.$generation->id.'.'.pathinfo($generation->path, PATHINFO_EXTENSION);
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
            'filename' => (Str::slug($reference->name) ?: 'reference-'.$reference->id).'.'.pathinfo($reference->path, PATHINFO_EXTENSION),
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
            'parent_url' => $generation->parent_id ? route('studio.images.show', $generation->parent_id) : null,
            'prompt' => $generation->prompt,
            'aspect_ratio' => $generation->aspect_ratio,
            'references' => $generation->reference_keys ?? [],
            'url' => route('studio.images.show', $generation),
            'download_url' => route('studio.images.download', $generation),
            'filename' => $this->generationFilename($generation),
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
