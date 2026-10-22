<?php

namespace App\Services;

use App\Enums\ContentFormat;
use App\Models\Campaign;
use App\Models\ContentItem;
use App\Models\ContentPlatformVersion;
use App\Models\PerformanceReport;
use App\Models\User;
use App\Models\WorkflowStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DashboardMetrics
{
    public function __construct(private PerformanceCalculator $calculator) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(User $user, array $filters): array
    {
        $from = Carbon::parse($filters['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($filters['to'] ?? now()->toDateString())->endOfDay();
        $closed = config('ops.closed_statuses');
        $staleDays = (int) config('ops.stale_after_days');

        $count = fn (callable $narrow) => $narrow($this->scoped($user, $filters))->count();

        $published = $this->scoped($user, $filters)
            ->whereBetween('published_at', [$from, $to])
            ->whereNotNull('deadline')
            ->get(['deadline', 'published_at', 'created_at']);

        $onTime = $published->filter(function (ContentItem $item) {
            return $item->published_at && $item->deadline
                && $item->published_at->lte($item->deadline->copy()->endOfDay());
        })->count();

        $turnaround = $published->isEmpty()
            ? null
            : round($published->avg(fn (ContentItem $item) => abs($item->created_at->diffInHours($item->published_at) / 24)), 1);

        $scheduledIds = ContentPlatformVersion::query()
            ->whereBetween('scheduled_at', [now()->startOfWeek(Carbon::MONDAY), now()->endOfWeek(Carbon::MONDAY)])
            ->whereHas('contentItem', function (Builder $query) use ($user, $filters) {
                $query->visibleTo($user);
                $this->applyFilters($query, $filters);
            })
            ->pluck('content_item_id')
            ->unique();

        $statusCounts = $this->scoped($user, $filters)
            ->selectRaw('status_id, count(*) as total')
            ->groupBy('status_id')
            ->get()
            ->pluck('total', 'status_id');

        $statuses = WorkflowStatus::query()->orderBy('sort_order')->get();

        $createdIds = $this->scoped($user, $filters)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('id');

        $byPlatform = ContentPlatformVersion::query()
            ->whereIn('content_item_id', $createdIds->isEmpty() ? [0] : $createdIds)
            ->selectRaw('platform_id, count(*) as total')
            ->groupBy('platform_id')
            ->get()
            ->pluck('total', 'platform_id');

        $platforms = \App\Models\Platform::query()->whereIn('id', $byPlatform->keys())->pluck('name', 'id');

        $byFormat = $this->scoped($user, $filters)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('format, count(*) as total')
            ->groupBy('format')
            ->get()
            ->pluck('total', 'format');

        $byCampaign = $this->scoped($user, $filters)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('campaign_id, count(*) as total')
            ->groupBy('campaign_id')
            ->get()
            ->pluck('total', 'campaign_id');

        $campaignNames = Campaign::query()->whereIn('id', $byCampaign->keys()->filter())->pluck('name', 'id');

        $workload = $this->scoped($user, $filters)
            ->whereHas('status', fn (Builder $query) => $query->whereNotIn('slug', $closed))
            ->selectRaw('owner_id, count(*) as total')
            ->groupBy('owner_id')
            ->get()
            ->pluck('total', 'owner_id');

        $ownerNames = User::query()->whereIn('id', $workload->keys()->filter())->pluck('name', 'id');

        $stale = $this->scoped($user, $filters)
            ->where('updated_at', '<', now()->subDays($staleDays))
            ->whereHas('status', fn (Builder $query) => $query->whereNotIn('slug', $closed))
            ->with(['status:id,name,slug,color', 'owner:id,name', 'brand:id,name'])
            ->orderBy('updated_at')
            ->limit(8)
            ->get();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'kpis' => [
                'active' => $count(fn (Builder $query) => $query->whereHas('status', fn (Builder $status) => $status->where('slug', '!=', 'archived'))),
                'awaiting_development' => $count(fn (Builder $query) => $query->whereHas('status', fn (Builder $status) => $status->whereIn('slug', ['inbox', 'ideation', 'ready_for_brief']))),
                'in_production' => $count(fn (Builder $query) => $query->whereHas('status', fn (Builder $status) => $status->whereIn('slug', ['ready_for_production', 'in_progress']))),
                'awaiting_review' => $count(fn (Builder $query) => $query->whereHas('status', fn (Builder $status) => $status->where('slug', 'internal_review'))),
                'revision_required' => $count(fn (Builder $query) => $query->whereHas('status', fn (Builder $status) => $status->where('slug', 'revision_required'))),
                'overdue' => $count(fn (Builder $query) => $query->whereDate('deadline', '<', now()->toDateString())->whereHas('status', fn (Builder $status) => $status->whereNotIn('slug', $closed))),
                'approved_ready' => $count(fn (Builder $query) => $query->whereHas('status', fn (Builder $status) => $status->where('slug', 'approved'))),
                'scheduled_this_week' => $scheduledIds->count(),
                'published_in_period' => $this->scoped($user, $filters)->whereBetween('published_at', [$from, $to])->count(),
            ],
            'on_time_rate' => $published->count() === 0 ? null : round($onTime / $published->count() * 100, 1),
            'on_time_sample' => $published->count(),
            'average_turnaround_days' => $turnaround,
            'by_status' => $statuses->map(fn (WorkflowStatus $status) => [
                'label' => $status->name,
                'slug' => $status->slug,
                'color' => $status->color,
                'total' => (int) ($statusCounts[$status->id] ?? 0),
            ])->filter(fn ($row) => $row['total'] > 0)->values(),
            'by_platform' => $byPlatform->map(fn ($total, $id) => [
                'label' => $platforms[$id] ?? 'Platform',
                'total' => (int) $total,
            ])->values(),
            'by_format' => $byFormat->map(fn ($total, $format) => [
                'label' => ContentFormat::tryFrom((string) $format)?->label() ?? ucfirst((string) $format),
                'total' => (int) $total,
            ])->values(),
            'by_campaign' => $byCampaign->map(fn ($total, $id) => [
                'label' => $id ? ($campaignNames[$id] ?? 'Campaign') : 'No campaign',
                'total' => (int) $total,
            ])->values(),
            'workload' => $workload->map(fn ($total, $id) => [
                'label' => $id ? ($ownerNames[$id] ?? 'Teammate') : 'Unassigned',
                'total' => (int) $total,
            ])->sortByDesc('total')->values(),
            'stale' => $stale->map(fn (ContentItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'reference_number' => $item->reference_number,
                'updated_at' => $item->updated_at?->toIso8601String(),
                'status' => $item->status?->only(['name', 'slug', 'color']),
                'owner' => $item->owner?->only(['id', 'name']),
                'brand' => $item->brand?->only(['id', 'name']),
            ])->values(),
            'performance' => $this->performance($user, $filters, $from, $to),
            'has_content' => $this->scoped($user, $filters)->exists(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function scoped(User $user, array $filters): Builder
    {
        return $this->applyFilters(ContentItem::query()->visibleTo($user), $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $table = $query->getModel()->getTable();

        if (! empty($filters['brand_id'])) {
            $query->where($table.'.brand_id', (int) $filters['brand_id']);
        }

        if (! empty($filters['campaign_id'])) {
            $query->where($table.'.campaign_id', (int) $filters['campaign_id']);
        }

        if (! empty($filters['user_id'])) {
            $query->where($table.'.owner_id', (int) $filters['user_id']);
        }

        if (! empty($filters['platform_id'])) {
            $query->whereHas('platformVersions', fn (Builder $versions) => $versions->where('platform_id', (int) $filters['platform_id']));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function performance(User $user, array $filters, Carbon $from, Carbon $to): array
    {
        $reports = PerformanceReport::query()
            ->whereDate('period_start', '<=', $to->toDateString())
            ->whereDate('period_end', '>=', $from->toDateString())
            ->whereHas('version.contentItem', function (Builder $query) use ($user, $filters) {
                $query->visibleTo($user);
                $this->applyFilters($query, $filters);
            })
            ->with(['version.platform:id,name', 'version.contentItem:id,title,brand_id,objective_id', 'version.contentItem.objective:id,name', 'version.contentItem.brand:id,name'])
            ->get();

        $grouped = $reports->groupBy('content_platform_version_id')->map(function ($rows) {
            /** @var PerformanceReport $first */
            $first = $rows->first();
            $version = $first->version;
            $recorded = fn (string $field) => $rows->contains(fn (PerformanceReport $row) => $row->{$field} !== null);
            $sum = [
                'impressions' => $recorded('impressions') ? (int) $rows->sum('impressions') : null,
                'likes' => $recorded('likes') ? (int) $rows->sum('likes') : null,
                'comments_count' => $recorded('comments_count') ? (int) $rows->sum('comments_count') : null,
                'shares' => $recorded('shares') ? (int) $rows->sum('shares') : null,
                'saves' => $recorded('saves') ? (int) $rows->sum('saves') : null,
                'link_clicks' => $recorded('link_clicks') ? (int) $rows->sum('link_clicks') : null,
                'leads' => $recorded('leads') ? (int) $rows->sum('leads') : null,
                'conversions' => $recorded('conversions') ? (int) $rows->sum('conversions') : null,
                'video_views_3s' => $recorded('video_views_3s') ? (int) $rows->sum('video_views_3s') : null,
                'ad_spend' => $recorded('ad_spend') ? (float) $rows->sum('ad_spend') : null,
                'revenue' => $recorded('revenue') ? (float) $rows->sum('revenue') : null,
            ];
            $metrics = $this->calculator->calculate($sum);

            return [
                'version_id' => $version->id,
                'content_id' => $version->content_item_id,
                'title' => $version->contentItem?->title,
                'platform' => $version->platform?->name,
                'objective' => $version->contentItem?->objective?->name,
                'brand' => $version->contentItem?->brand?->name,
                'metrics' => $metrics,
                'impressions' => $sum['impressions'],
            ];
        })->sortByDesc(fn ($row) => $row['metrics']['engagement_rate'] ?? -1)->values();

        $byObjective = $grouped->groupBy(fn ($row) => $row['objective'] ?? 'No objective')->map(function ($rows, $objective) {
            $rates = $rows->map(fn ($row) => $row['metrics']['engagement_rate'])->filter(fn ($rate) => $rate !== null);

            return [
                'objective' => $objective,
                'pieces' => $rows->count(),
                'avg_engagement_rate' => $rates->isEmpty() ? null : round($rates->avg(), 2),
            ];
        })->values();

        return [
            'demo_only' => $reports->isNotEmpty() && $reports->every(fn (PerformanceReport $row) => $row->source === 'demo_seed'),
            'report_count' => $reports->count(),
            'best' => $grouped->take(5)->values(),
            'by_objective' => $byObjective,
        ];
    }
}
