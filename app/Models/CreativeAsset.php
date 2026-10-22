<?php

namespace App\Models;

use App\Enums\AssetKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CreativeAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'content_item_id',
        'platform_version_id',
        'uploaded_by',
        'name',
        'original_filename',
        'disk',
        'path',
        'mime_type',
        'extension',
        'size',
        'kind',
        'description',
        'version',
        'parent_asset_id',
        'is_final',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version' => 'integer',
            'is_final' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function platformVersion(): BelongsTo
    {
        return $this->belongsTo(ContentPlatformVersion::class, 'platform_version_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_asset_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_asset_id');
    }

    public function kindLabel(): string
    {
        return AssetKind::tryFrom((string) $this->kind)?->label() ?? 'Other';
    }

    public function isPreviewable(): bool
    {
        return str_starts_with($this->mime_type, 'image/') || str_starts_with($this->mime_type, 'video/');
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }
}
