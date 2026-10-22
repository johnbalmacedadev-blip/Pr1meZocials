<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\ContentPlatformVersion;
use App\Services\ContentQuery;
use App\Support\ContentPresenter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request, ContentQuery $contentQuery, ContentPresenter $presenter): Response
    {
        $this->authorize('viewAny', ContentItem::class);

        $anchor = Carbon::parse($request->input('date', now()->toDateString()));
        $view = in_array($request->input('view'), ['month', 'week', 'day', 'list'], true) ? $request->input('view') : 'month';
        $focus = $request->input('focus') === 'deadline' ? 'deadline' : 'publish';
        [$start, $end] = $this->range($anchor, $view);
        $filters = $request->only(['brand_id', 'campaign_id', 'platform_id', 'owner_id', 'status', 'format']);
        $visibleIds = $contentQuery->make($request->user(), $filters, false)->select('content_items.id');
        $threshold = (int) config('ops.schedule_conflict_threshold');

        $versions = ContentPlatformVersion::query()
            ->whereIn('content_item_id', $visibleIds)
            ->whereBetween('scheduled_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->with(['platform:id,name,slug,color', 'contentItem:id,title,reference_number,brand_id,owner_id,status_id,deadline,format', 'contentItem.brand:id,name', 'contentItem.owner:id,name', 'contentItem.status:id,name,slug,color'])
            ->orderBy('scheduled_at')
            ->get();

        $conflictKeys = $versions
            ->groupBy(fn (ContentPlatformVersion $version) => $version->contentItem->brand_id.'|'.$version->platform_id.'|'.$version->scheduled_at->toDateString())
            ->filter(fn ($group) => $group->count() >= $threshold)
            ->keys();

        $events = $versions->map(function (ContentPlatformVersion $version) use ($conflictKeys, $presenter) {
            $key = $version->contentItem->brand_id.'|'.$version->platform_id.'|'.$version->scheduled_at->toDateString();

            return [
                'id' => 'version-'.$version->id,
                'version_id' => $version->id,
                'content_id' => $version->content_item_id,
                'title' => $version->contentItem->title,
                'reference_number' => $version->contentItem->reference_number,
                'date' => $version->scheduled_at->toDateString(),
                'time' => $version->scheduled_at->format('H:i'),
                'kind' => 'publish',
                'format' => $version->format,
                'format_label' => $version->formatLabel(),
                'platform' => $version->platform?->only(['id', 'name', 'slug', 'color']),
                'status' => $version->contentItem->status?->only(['name', 'slug', 'color']),
                'brand' => $version->contentItem->brand?->name,
                'owner' => $presenter->person($version->contentItem->owner),
                'publishing_status' => $version->publishing_status,
                'conflict' => $conflictKeys->contains($key),
                'deadline' => $version->contentItem->deadline?->toDateString(),
            ];
        })->values();

        $deadlines = ContentItem::query()
            ->whereIn('id', $visibleIds)
            ->whereBetween('deadline', [$start->toDateString(), $end->toDateString()])
            ->with(['status:id,name,slug,color', 'owner:id,name', 'brand:id,name'])
            ->orderBy('deadline')
            ->get()
            ->map(fn (ContentItem $item) => [
                'id' => 'deadline-'.$item->id,
                'version_id' => null,
                'content_id' => $item->id,
                'title' => $item->title,
                'reference_number' => $item->reference_number,
                'date' => $item->deadline?->toDateString(),
                'time' => null,
                'kind' => 'deadline',
                'format' => $item->format,
                'format_label' => $item->formatLabel(),
                'platform' => null,
                'status' => $item->status?->only(['name', 'slug', 'color']),
                'brand' => $item->brand?->name,
                'owner' => $presenter->person($item->owner),
                'publishing_status' => null,
                'conflict' => false,
                'deadline' => $item->deadline?->toDateString(),
            ])->values();

        return Inertia::render('Calendar/Index', [
            'events' => $events,
            'deadlines' => $deadlines,
            'view' => $view,
            'focus' => $focus,
            'date' => $anchor->toDateString(),
            'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'filters' => $filters,
            'canReschedule' => $request->user()->hasPermission('content.publish'),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Carbon $anchor, string $view): array
    {
        return match ($view) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'week' => [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfWeek(Carbon::MONDAY)],
            'list' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            default => [
                $anchor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
                $anchor->copy()->endOfMonth()->endOfWeek(Carbon::MONDAY),
            ],
        };
    }
}
