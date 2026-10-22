<?php

namespace App\Http\Controllers;

use App\Models\ContentPlatformVersion;
use App\Models\PerformanceReport;
use App\Services\DashboardMetrics;
use App\Services\PerformanceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    public function index(Request $request, DashboardMetrics $metrics, PerformanceCalculator $calculator): Response
    {
        abort_unless($request->user()->hasPermission('performance.view') || $request->user()->hasPermission('reports.view'), 403);

        $filters = $request->only(['from', 'to', 'brand_id', 'campaign_id', 'platform_id', 'user_id']);

        $reports = PerformanceReport::query()
            ->whereHas('version.contentItem', function ($query) use ($request, $filters) {
                $query->visibleTo($request->user());

                if (! empty($filters['brand_id'])) {
                    $query->where('brand_id', (int) $filters['brand_id']);
                }
            })
            ->with(['version.platform:id,name', 'version.contentItem:id,title,reference_number,brand_id', 'version.contentItem.brand:id,name', 'recorder:id,name'])
            ->latest('period_end')
            ->paginate(20)
            ->withQueryString();

        $reports->getCollection()->transform(function (PerformanceReport $report) use ($calculator) {
            return [
                'id' => $report->id,
                'source' => $report->source,
                'period_start' => $report->period_start?->toDateString(),
                'period_end' => $report->period_end?->toDateString(),
                'reach' => $report->reach,
                'impressions' => $report->impressions,
                'views' => $report->views,
                'likes' => $report->likes,
                'comments_count' => $report->comments_count,
                'shares' => $report->shares,
                'saves' => $report->saves,
                'link_clicks' => $report->link_clicks,
                'leads' => $report->leads,
                'conversions' => $report->conversions,
                'revenue' => $report->revenue,
                'ad_spend' => $report->ad_spend,
                'notes' => $report->notes,
                'metrics' => $calculator->calculate($report->toArray()),
                'content' => [
                    'id' => $report->version?->contentItem?->id,
                    'title' => $report->version?->contentItem?->title,
                    'reference_number' => $report->version?->contentItem?->reference_number,
                ],
                'platform' => $report->version?->platform?->name,
                'version_id' => $report->content_platform_version_id,
                'recorder' => $report->recorder?->name,
            ];
        });

        $versions = ContentPlatformVersion::query()
            ->whereHas('contentItem', fn ($query) => $query->visibleTo($request->user())->whereHas('status', fn ($status) => $status->whereIn('slug', ['approved', 'published'])))
            ->with(['platform:id,name', 'contentItem:id,title,reference_number'])
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (ContentPlatformVersion $version) => [
                'id' => $version->id,
                'label' => $version->contentItem->reference_number.' · '.$version->contentItem->title.' · '.$version->platform->name,
            ]);

        return Inertia::render('Performance/Index', [
            'reports' => $reports,
            'summary' => $metrics->build($request->user(), $filters)['performance'],
            'versions' => $versions,
            'filters' => $filters,
            'canManage' => $request->user()->hasPermission('performance.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('performance.manage'), 403);

        $data = $request->validate([
            'content_platform_version_id' => ['required', 'integer', 'exists:content_platform_versions,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'reach' => ['nullable', 'integer', 'min:0'],
            'impressions' => ['nullable', 'integer', 'min:0'],
            'views' => ['nullable', 'integer', 'min:0'],
            'video_views_3s' => ['nullable', 'integer', 'min:0'],
            'watch_time_seconds' => ['nullable', 'integer', 'min:0'],
            'likes' => ['nullable', 'integer', 'min:0'],
            'comments_count' => ['nullable', 'integer', 'min:0'],
            'shares' => ['nullable', 'integer', 'min:0'],
            'saves' => ['nullable', 'integer', 'min:0'],
            'link_clicks' => ['nullable', 'integer', 'min:0'],
            'leads' => ['nullable', 'integer', 'min:0'],
            'conversions' => ['nullable', 'integer', 'min:0'],
            'revenue' => ['nullable', 'numeric', 'min:0'],
            'ad_spend' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $version = ContentPlatformVersion::query()->with('contentItem')->findOrFail($data['content_platform_version_id']);
        $this->authorize('view', $version->contentItem);

        $data['source'] = 'manual';
        $data['recorded_by'] = $request->user()->id;
        $version->performanceReports()->create($data);

        return back()->with('success', 'Performance recorded from a manual entry. No social platform is connected.');
    }

    public function destroy(Request $request, PerformanceReport $report): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('performance.manage'), 403);
        $report->load('version.contentItem');
        $this->authorize('view', $report->version->contentItem);

        if ($report->source === 'demo_seed' && ! $request->user()->isAdmin()) {
            abort(403, 'Demo seed metrics can be removed by an administrator.');
        }

        $report->delete();

        return back()->with('success', 'Performance report removed.');
    }
}
