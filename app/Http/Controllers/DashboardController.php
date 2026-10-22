<?php

namespace App\Http\Controllers;

use App\Models\ContentPlatformVersion;
use App\Services\ContentQuery;
use App\Services\DashboardMetrics;
use App\Support\FormOptions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardMetrics $metrics, ContentQuery $contentQuery): Response
    {
        $filters = $request->only(['from', 'to', 'brand_id', 'campaign_id', 'platform_id', 'user_id']);

        return Inertia::render('Dashboard', [
            'metrics' => $metrics->build($request->user(), $filters),
            'filters' => $filters,
            'options' => FormOptions::for($request->user()),
            'schedule' => $this->schedule($request, $contentQuery),
        ]);
    }

    /**
     * @return array{month: string, label: string, events: list<array<string, mixed>>}
     */
    private function schedule(Request $request, ContentQuery $contentQuery): array
    {
        $month = Carbon::parse($request->input('month', now()->toDateString()))->startOfMonth();
        $start = $month->copy()->startOfWeek(Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::MONDAY);
        $visibleIds = $contentQuery->make($request->user(), [
            'brand_id' => $request->input('brand_id'),
            'campaign_id' => $request->input('campaign_id'),
            'owner_id' => $request->input('user_id'),
        ], false)->select('content_items.id');

        $events = ContentPlatformVersion::query()
            ->whereIn('content_item_id', $visibleIds)
            ->whereBetween('scheduled_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->with(['platform:id,name', 'contentItem:id,title,brand_id', 'contentItem.brand:id,name'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (ContentPlatformVersion $version) => [
                'id' => $version->id,
                'content_id' => $version->content_item_id,
                'title' => $version->contentItem->title,
                'date' => $version->scheduled_at->toDateString(),
                'time' => $version->scheduled_at->format('H:i'),
                'platform' => $version->platform?->name,
                'brand' => $version->contentItem->brand?->name,
            ])
            ->values();

        return [
            'month' => $month->toDateString(),
            'label' => $month->format('F Y'),
            'events' => $events->all(),
        ];
    }
}
