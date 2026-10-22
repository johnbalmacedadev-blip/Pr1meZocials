<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\InspirationItem;
use App\Models\Platform;
use App\Models\User;
use App\Rules\HttpUrl;
use App\Support\InspirationEmbed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InspirationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeBoard($request, 'view');

        $archived = $request->boolean('archived');

        $items = InspirationItem::query()
            ->where('brand_id', $this->defaultBrand($request)->id)
            ->when($archived, fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->with(['platform:id,name,slug,color', 'user:id,name', 'brand:id,name'])
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Inspiration/Index', [
            'items' => [
                'data' => $items->getCollection()->map(fn (InspirationItem $item) => $this->present($item, $request->user()))->values(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'prev' => $items->previousPageUrl(),
                'next' => $items->nextPageUrl(),
            ],
            'canSave' => $request->user()->hasPermission('content.create'),
            'archived' => $archived,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeBoard($request, 'create');

        $data = $this->validatedItem($request);
        $brand = $this->defaultBrand($request);

        InspirationItem::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $data['platform_id'],
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'url' => $data['url'],
        ]);

        return back()->with('success', 'Inspiration saved.');
    }

    public function update(Request $request, InspirationItem $inspiration): RedirectResponse
    {
        $this->authorizeBoard($request, 'view');
        abort_unless($this->canManage($request->user(), $inspiration), 403);

        $data = $this->validatedItem($request);
        $inspiration->update([
            'platform_id' => $data['platform_id'],
            'name' => $data['name'],
            'url' => $data['url'],
        ]);

        return back()->with('success', 'Inspiration updated.');
    }

    public function archive(Request $request, InspirationItem $inspiration): RedirectResponse
    {
        $this->authorizeBoard($request, 'view');
        abort_unless($this->canManage($request->user(), $inspiration), 403);

        $restore = $inspiration->archived_at !== null;
        $inspiration->forceFill(['archived_at' => $restore ? null : now()])->save();

        return back()->with('success', $restore ? 'Inspiration restored to the active board.' : 'Inspiration archived.');
    }

    public function destroy(Request $request, InspirationItem $inspiration): RedirectResponse
    {
        $this->authorizeBoard($request, 'view');
        abort_unless($this->canManage($request->user(), $inspiration), 403);

        $inspiration->delete();

        return back()->with('success', 'Inspiration removed.');
    }

    private function authorizeBoard(Request $request, string $action): void
    {
        $permission = $action === 'create' ? 'content.create' : 'content.view';
        $allowed = $request->user()->hasPermission($permission) || $request->user()->hasPermission('content.view.any');

        abort_unless($allowed, 403);
    }

    private function defaultBrand(Request $request): Brand
    {
        $ids = $request->user()->accessibleBrandIds() ?: [0];

        $brand = Brand::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderByRaw("case when name = 'Prime Z' then 0 else 1 end")
            ->orderBy('name')
            ->first();

        abort_if($brand === null, 422, 'Add a brand before saving inspiration.');

        return $brand;
    }

    /**
     * @return array{name: string, platform_id: int, url: string}
     */
    private function validatedItem(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'platform_id' => ['required', 'integer', 'exists:platforms,id'],
            'url' => ['required', 'string', 'max:2048', new HttpUrl],
        ]);

        $platform = Platform::query()->whereKey($data['platform_id'])->where('is_active', true)->first();

        if (! $platform) {
            throw ValidationException::withMessages([
                'platform_id' => 'Choose an active social platform.',
            ]);
        }

        return [
            'name' => trim($data['name']),
            'platform_id' => $platform->id,
            'url' => $data['url'],
        ];
    }

    private function canManage(User $user, InspirationItem $item): bool
    {
        if (! $user->canAccessBrand($item->brand_id)) {
            return false;
        }

        return $item->user_id === $user->id
            || $user->hasPermission('content.create')
            || $user->hasPermission('content.update.any')
            || $user->hasPermission('content.delete');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(InspirationItem $item, User $user): array
    {
        $embed = InspirationEmbed::resolve($item->platform?->slug, $item->url);
        $canManage = $this->canManage($user, $item);

        return [
            'id' => $item->id,
            'name' => $item->name,
            'url' => $item->url,
            'embed_url' => $embed['embed_url'],
            'kind' => $embed['kind'],
            'created_at' => $item->created_at?->toIso8601String(),
            'can_edit' => $canManage,
            'can_delete' => $canManage,
            'platform' => $item->platform ? [
                'id' => $item->platform->id,
                'name' => $item->platform->name,
                'slug' => $item->platform->slug,
                'color' => $item->platform->color,
            ] : null,
            'user' => $item->user ? ['id' => $item->user->id, 'name' => $item->user->name] : null,
        ];
    }
}
