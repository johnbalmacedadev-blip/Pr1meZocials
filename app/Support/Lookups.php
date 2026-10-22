<?php

namespace App\Support;

use App\Enums\AssetKind;
use App\Enums\ContentFormat;
use App\Enums\Distribution;
use App\Enums\FunnelStage;
use App\Enums\Priority;
use App\Enums\PublishingStatus;
use App\Models\Platform;
use App\Models\WorkflowStatus;
use App\Services\PerformanceCalculator;
use Illuminate\Support\Facades\Cache;

class Lookups
{
    /**
     * @return array<string, mixed>
     */
    public static function shared(): array
    {
        return Cache::remember('ops.lookups', 60, function () {
            return [
                'statuses' => WorkflowStatus::query()
                    ->orderBy('sort_order')
                    ->get(['id', 'name', 'slug', 'color', 'sort_order', 'enter_permission', 'allowed_transitions', 'required_fields']),
                'formats' => ContentFormat::options(),
                'priorities' => Priority::options(),
                'funnel_stages' => FunnelStage::options(),
                'distributions' => Distribution::options(),
                'publishing_statuses' => PublishingStatus::options(),
                'asset_kinds' => AssetKind::options(),
                'platforms' => Platform::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'color']),
                'formulas' => PerformanceCalculator::FORMULAS,
            ];
        });
    }

    public static function flush(): void
    {
        Cache::forget('ops.lookups');
    }
}
