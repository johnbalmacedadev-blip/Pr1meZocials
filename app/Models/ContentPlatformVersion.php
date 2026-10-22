<?php

namespace App\Models;

use App\Enums\ContentFormat;
use App\Enums\PublishingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPlatformVersion extends Model
{
    protected $fillable = [
        'content_item_id',
        'platform_id',
        'social_account_id',
        'creative_asset_id',
        'format',
        'headline',
        'caption',
        'hashtags',
        'call_to_action',
        'instructions',
        'scheduled_at',
        'publishing_status',
        'published_url',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function creativeAsset(): BelongsTo
    {
        return $this->belongsTo(CreativeAsset::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(CreativeAsset::class, 'platform_version_id');
    }

    public function performanceReports(): HasMany
    {
        return $this->hasMany(PerformanceReport::class);
    }

    public function publishingRecords(): HasMany
    {
        return $this->hasMany(PublishingRecord::class);
    }

    public function formatLabel(): string
    {
        return ContentFormat::tryFrom((string) $this->format)?->label() ?? ucfirst((string) $this->format);
    }

    public function publishingStatusLabel(): string
    {
        return PublishingStatus::tryFrom((string) $this->publishing_status)?->label()
            ?? ucfirst((string) $this->publishing_status);
    }
}
