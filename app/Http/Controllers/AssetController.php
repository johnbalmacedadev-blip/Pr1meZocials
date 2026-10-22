<?php

namespace App\Http\Controllers;

use App\Enums\AssetKind;
use App\Models\ContentItem;
use App\Models\CreativeAsset;
use App\Services\AssetService;
use App\Support\ContentPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public function index(Request $request, ContentPresenter $presenter): Response
    {
        abort_unless($request->user()->hasPermission('assets.view'), 403);

        $brandIds = $request->user()->accessibleBrandIds() ?: [0];

        $assets = CreativeAsset::query()
            ->where(function ($query) use ($request, $brandIds) {
                $query->whereHas('contentItem', fn ($content) => $content->visibleTo($request->user()))
                    ->orWhere(fn ($library) => $library->whereNull('content_item_id')->whereIn('brand_id', $brandIds));
            })
            ->when($request->filled('kind'), fn ($query) => $query->where('kind', $request->string('kind')))
            ->when($request->filled('brand_id'), fn ($query) => $query->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.addcslashes($request->string('q')->toString(), '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('original_filename', 'like', $term));
            })
            ->with(['uploader:id,name', 'contentItem:id,title', 'brand:id,name'])
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Assets/Index', [
            'assets' => [
                'data' => $assets->getCollection()->map(fn (CreativeAsset $asset) => $presenter->asset($asset))->values(),
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'total' => $assets->total(),
                'prev' => $assets->previousPageUrl(),
                'next' => $assets->nextPageUrl(),
            ],
            'filters' => $request->only(['q', 'kind', 'brand_id']),
            'kinds' => AssetKind::options(),
        ]);
    }

    public function store(Request $request, ContentItem $content, AssetService $assets): RedirectResponse
    {
        $this->authorize('update', $content);
        abort_unless($request->user()->hasPermission('assets.upload'), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('ops.upload_max_kb')],
            'name' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['nullable', Rule::enum(AssetKind::class)],
            'parent_asset_id' => ['nullable', 'integer'],
            'platform_version_id' => ['nullable', 'integer', Rule::exists('content_platform_versions', 'id')->where('content_item_id', $content->id)],
            'is_final' => ['nullable', 'boolean'],
        ]);

        $assets->store($content, $request->user(), $request->file('file'), $data);

        return back()->with('success', 'Asset uploaded to private storage.');
    }

    public function download(CreativeAsset $asset): StreamedResponse
    {
        $this->authorize('view', $asset);
        abort_unless($asset->existsOnDisk(), 404);

        return Storage::disk($asset->disk)->download($asset->path, $this->safeFilename($asset->original_filename));
    }

    public function preview(CreativeAsset $asset): StreamedResponse
    {
        $this->authorize('view', $asset);
        abort_unless($asset->isPreviewable() && $asset->existsOnDisk(), 404);

        return Storage::disk($asset->disk)->response($asset->path, $this->safeFilename($asset->original_filename), [
            'Content-Disposition' => 'inline; filename="'.$this->safeFilename($asset->original_filename).'"',
        ]);
    }

    public function destroy(CreativeAsset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);
        $asset->delete();

        return back()->with('success', 'Asset removed from the library.');
    }

    private function safeFilename(string $filename): string
    {
        $clean = str_replace(["\r", "\n", '"', '\\'], '', $filename);

        return $clean === '' ? 'download' : $clean;
    }
}
