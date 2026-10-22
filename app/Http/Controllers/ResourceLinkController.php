<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\ResourceLink;
use App\Models\User;
use App\Rules\GoogleDriveFolderUrl;
use App\Services\GoogleDriveException;
use App\Services\GoogleDriveFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourceLinkController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeBoard($request);

        $search = trim((string) $request->input('q', ''));
        $view = $request->input('view') === 'list' ? 'list' : 'cards';

        $items = ResourceLink::query()
            ->where('brand_id', $this->defaultBrand($request)->id)
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where('name', 'like', $like);
            })
            ->with('user:id,name')
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Resources/Index', [
            'items' => [
                'data' => $items->getCollection()->map(fn (ResourceLink $item) => $this->present($item, $request->user()))->values(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'prev' => $items->previousPageUrl(),
                'next' => $items->nextPageUrl(),
            ],
            'filters' => ['q' => $search, 'view' => $view],
            'canSave' => $request->user()->hasPermission('content.create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeBoard($request);
        abort_unless($request->user()->hasPermission('content.create'), 403);

        $data = $this->validatedItem($request);

        ResourceLink::query()->create([
            'brand_id' => $this->defaultBrand($request)->id,
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'url' => $data['url'],
        ]);

        return back()->with('success', 'Folder saved.');
    }

    public function update(Request $request, ResourceLink $resourceLink): RedirectResponse
    {
        $this->authorizeBoard($request);
        abort_unless($this->canManage($request->user(), $resourceLink), 403);

        $data = $this->validatedItem($request);
        $resourceLink->update([
            'name' => $data['name'],
            'url' => $data['url'],
        ]);

        return back()->with('success', 'Folder updated.');
    }

    public function files(Request $request, ResourceLink $resourceLink, GoogleDriveFolder $drive): JsonResponse
    {
        $root = $this->authorizedFolderId($request, $resourceLink, $drive);
        $folder = (string) $request->query('folder', $root);
        abort_unless($drive->validId($folder) && $drive->folderIsInside($root, $folder), 404);

        try {
            $entries = $drive->listFolder($folder);
        } catch (GoogleDriveException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'folder_id' => $folder,
            'entries' => collect($entries)->map(fn (array $entry) => [
                'id' => $entry['id'],
                'name' => $entry['name'],
                'kind' => $entry['kind'],
                'mime' => $entry['mime'],
                'modified' => $entry['modified'],
                'previewable' => $entry['kind'] === 'file' && $drive->isPreviewable($entry['mime']),
            ])->values(),
        ]);
    }

    public function preview(Request $request, ResourceLink $resourceLink, string $file, GoogleDriveFolder $drive): StreamedResponse
    {
        $found = $this->authorizedFile($request, $resourceLink, $file, $drive);

        try {
            return $this->stream($drive->fetchPreview($found, $request->header('Range')), $found['name'], false);
        } catch (GoogleDriveException $exception) {
            abort(422, $exception->getMessage());
        }
    }

    public function download(Request $request, ResourceLink $resourceLink, string $file, GoogleDriveFolder $drive): StreamedResponse
    {
        $found = $this->authorizedFile($request, $resourceLink, $file, $drive);

        try {
            return $this->stream($drive->fetchDownload($found, $request->header('Range')), $found['name'], true);
        } catch (GoogleDriveException $exception) {
            abort(422, $exception->getMessage());
        }
    }

    public function destroy(Request $request, ResourceLink $resourceLink): RedirectResponse
    {
        $this->authorizeBoard($request);
        abort_unless($this->canManage($request->user(), $resourceLink), 403);

        $resourceLink->delete();

        return back()->with('success', 'Folder removed.');
    }

    private function authorizedFolderId(Request $request, ResourceLink $resourceLink, GoogleDriveFolder $drive): string
    {
        $this->authorizeBoard($request);
        abort_unless($request->user()->canAccessBrand($resourceLink->brand_id), 403);

        $root = $drive->idFromUrl($resourceLink->url);
        abort_unless(is_string($root) && $drive->validId($root), 422, 'This folder link is not a Google Drive folder.');

        return $root;
    }

    /**
     * @return array{id: string, name: string, kind: string, mime: string|null, modified: string|null, thumbnail: string|null}
     */
    private function authorizedFile(Request $request, ResourceLink $resourceLink, string $file, GoogleDriveFolder $drive): array
    {
        $root = $this->authorizedFolderId($request, $resourceLink, $drive);
        $folder = (string) $request->query('folder', $root);

        try {
            $found = $drive->findFile($root, $folder, $file);
        } catch (GoogleDriveException $exception) {
            abort(422, $exception->getMessage());
        }

        abort_unless(is_array($found), 404);

        return $found;
    }

    /**
     * @param  \Psr\Http\Message\ResponseInterface  $upstream
     */
    private function stream($upstream, string $filename, bool $attachment): StreamedResponse
    {
        $type = $upstream->getHeaderLine('Content-Type');
        $type = trim(explode(';', $type)[0] ?: '') ?: 'application/octet-stream';
        $safe = str_replace(["\r", "\n", '"', '\\'], '', $filename);
        $safe = $safe !== '' ? $safe : 'download';
        $disposition = ($attachment ? 'attachment' : 'inline').'; filename="'.$safe.'"';

        $headers = [
            'Content-Type' => $type,
            'Content-Disposition' => $disposition,
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ];

        foreach (['Content-Length', 'Content-Range'] as $name) {
            if ($upstream->hasHeader($name)) {
                $headers[$name] = $upstream->getHeaderLine($name);
            }
        }

        return response()->stream(function () use ($upstream) {
            $body = $upstream->getBody();
            while (! $body->eof()) {
                echo $body->read(1024 * 128);
                if (connection_aborted()) {
                    break;
                }
                flush();
            }
        }, $upstream->getStatusCode() ?: 200, $headers);
    }

    private function authorizeBoard(Request $request): void
    {
        $allowed = $request->user()->hasPermission('content.view') || $request->user()->hasPermission('content.view.any');

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

        abort_if($brand === null, 422, 'Add a brand before saving a resource.');

        return $brand;
    }

    /**
     * @return array{name: string, url: string}
     */
    private function validatedItem(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:2048', new GoogleDriveFolderUrl],
        ]);

        return [
            'name' => trim($data['name']),
            'url' => $data['url'],
        ];
    }

    private function canManage(User $user, ResourceLink $item): bool
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
    private function present(ResourceLink $item, User $user): array
    {
        $canManage = $this->canManage($user, $item);

        return [
            'id' => $item->id,
            'name' => $item->name,
            'url' => $item->url,
            'created_at' => $item->created_at?->toIso8601String(),
            'can_edit' => $canManage,
            'can_delete' => $canManage,
            'user' => $item->user ? ['id' => $item->user->id, 'name' => $item->user->name] : null,
        ];
    }
}
