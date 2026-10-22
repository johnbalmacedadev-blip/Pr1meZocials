<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReport extends Model
{
    protected $fillable = [
        'content_platform_version_id',
        'period_start',
        'period_end',
        'source',
        'reach',
        'impressions',
        'views',
        'video_views_3s',
        'watch_time_seconds',
        'likes',
        'comments_count',
        'shares',
        'saves',
        'link_clicks',
        'leads',
        'conversions',
        'revenue',
        'ad_spend',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'revenue' => 'decimal:2',
            'ad_spend' => 'decimal:2',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContentPlatformVersion::class, 'content_platform_version_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
