<?php

namespace App\Services;

use App\Enums\AssetKind;
use App\Models\ContentItem;
use App\Models\ContentPlatformVersion;
use App\Models\CreativeAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssetService
{
    public function __construct(private ActivityLogger $activity) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function store(ContentItem $item, User $actor, UploadedFile $file, array $meta = []): CreativeAsset
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = config('ops.allowed_uploads');

        if (! isset($allowed[$extension])) {
            throw ValidationException::withMessages([
                'file' => 'This file type is not allowed.',
            ]);
        }

        $mime = (string) $file->getMimeType();

        if (! in_array($mime, $allowed[$extension], true)) {
            throw ValidationException::withMessages([
                'file' => 'The file contents do not match its extension.',
            ]);
        }

        return DB::transaction(function () use ($item, $actor, $file, $meta, $extension, $mime) {
            $parent = null;
            $version = 1;

            if (! empty($meta['parent_asset_id'])) {
                $parent = CreativeAsset::query()
                    ->where('content_item_id', $item->id)
                    ->find($meta['parent_asset_id']);

                if (! $parent) {
                    throw ValidationException::withMessages([
                        'parent_asset_id' => 'The previous version could not be found on this content item.',
                    ]);
                }

                $version = $parent->version + 1;
            }

            $filename = Str::uuid()->toString().'.'.$extension;
            $path = $file->storeAs('assets/'.$item->brand_id.'/'.$item->id, $filename, ['disk' => 'local']);

            if ($path === false) {
                throw ValidationException::withMessages([
                    'file' => 'The file could not be stored.',
                ]);
            }

            $kind = $meta['kind'] ?? $this->inferKind($mime);

            $asset = $item->assets()->create([
                'brand_id' => $item->brand_id,
                'platform_version_id' => $meta['platform_version_id'] ?? null,
                'uploaded_by' => $actor->id,
                'name' => $meta['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'original_filename' => $file->getClientOriginalName(),
                'disk' => 'local',
                'path' => $path,
                'mime_type' => $mime,
                'extension' => $extension,
                'size' => $file->getSize() ?: 0,
                'kind' => $kind,
                'description' => $meta['description'] ?? null,
                'version' => $version,
                'parent_asset_id' => $parent?->id,
                'is_final' => (bool) ($meta['is_final'] ?? false),
            ]);

            if ($asset->is_final && $asset->platform_version_id) {
                ContentPlatformVersion::query()
                    ->where('content_item_id', $item->id)
                    ->whereKey($asset->platform_version_id)
                    ->update(['creative_asset_id' => $asset->id]);
            }

            $this->activity->log($item, $actor, 'asset_uploaded', 'Uploaded '.$asset->original_filename.' (v'.$asset->version.').', [
                'asset_id' => $asset->id,
                'version' => $asset->version,
                'kind' => $asset->kind,
            ]);

            return $asset;
        });
    }

    private function inferKind(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return AssetKind::Image->value;
        }

        if (str_starts_with($mime, 'video/')) {
            return AssetKind::Video->value;
        }

        if (in_array($mime, ['application/pdf', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], true)) {
            return AssetKind::Script->value;
        }

        return AssetKind::Other->value;
    }
}
