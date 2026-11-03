<?php

namespace App\Models;

use App\Support\PhilippineTime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPost extends Model
{
    protected $fillable = [
        'social_account_id',
        'user_id',
        'content_item_id',
        'caption',
        'media_type',
        'media_path',
        'media_name',
        'media_files',
        'facebook_post_id',
        'tiktok_publish_id',
        'cross_post_of_id',
        'permalink_url',
        'status',
        'error_message',
        'likes_count',
        'comments_count',
        'shares_count',
        'views_count',
        'reach',
        'engagement_rate',
        'metrics_synced_at',
        'published_at',
        'calendar_at',
        'scheduled_publish_at',
    ];

    protected function casts(): array
    {
        return [
            'media_files' => 'array',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'shares_count' => 'integer',
            'views_count' => 'integer',
            'reach' => 'integer',
            'engagement_rate' => 'float',
            'metrics_synced_at' => 'datetime',
            'published_at' => 'datetime',
            'calendar_at' => 'datetime',
            'scheduled_publish_at' => 'datetime',
        ];
    }

    /**
     * Where this post shows on calendars, in Philippine time.
     */
    public function calendarTime(): ?CarbonInterface
    {
        $at = $this->calendar_at ?? $this->scheduled_publish_at ?? $this->published_at;

        return $at ? PhilippineTime::of($at) : null;
    }

    /**
     * Posts that show on calendars between two UTC moments.
     */
    public function scopeOnCalendarBetween(Builder $query, CarbonInterface $startUtc, CarbonInterface $endUtc): void
    {
        $query->where(function (Builder $query) use ($startUtc, $endUtc) {
            $query->where(function (Builder $scheduled) use ($startUtc, $endUtc) {
                $scheduled->where('status', 'scheduled')->whereBetween('scheduled_publish_at', [$startUtc, $endUtc]);
            })->orWhere(function (Builder $published) use ($startUtc, $endUtc) {
                $published->where('status', 'published')->where(function (Builder $when) use ($startUtc, $endUtc) {
                    $when->whereBetween('calendar_at', [$startUtc, $endUtc])
                        ->orWhere(fn (Builder $real) => $real->whereNull('calendar_at')->whereBetween('published_at', [$startUtc, $endUtc]));
                });
            });
        });
    }

    /**
     * @return list<array{path: string, name: string}>
     */
    public function mediaItems(): array
    {
        $files = $this->media_files;
        if (is_array($files) && $files !== []) {
            $items = [];
            foreach ($files as $file) {
                $path = is_array($file) ? (string) ($file['path'] ?? '') : '';
                if ($path === '') {
                    continue;
                }
                $items[] = [
                    'path' => $path,
                    'name' => is_array($file) ? (string) ($file['name'] ?? 'media') : 'media',
                ];
            }

            if ($items !== []) {
                return $items;
            }
        }

        if ($this->media_path) {
            return [[
                'path' => (string) $this->media_path,
                'name' => (string) ($this->media_name ?: 'media'),
            ]];
        }

        return [];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function crossPostOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cross_post_of_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && (filled($this->facebook_post_id) || filled($this->tiktok_publish_id));
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' && $this->scheduled_publish_at !== null;
    }
}
