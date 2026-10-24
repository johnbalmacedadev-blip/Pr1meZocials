<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\PostingScheduleEntry;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\PostingScheduleSlots;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PostingScheduleController extends Controller
{
    /** @var array<string, int> */
    private const WEEKDAYS = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 7,
    ];

    /** @var list<string> */
    private const SLOTS = PostingScheduleSlots::KEYS;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ContentItem::class);

        $user = $request->user();
        $week = $this->resolveWeek($request);

        $entries = PostingScheduleEntry::query()
            ->with(['contentItem:id,title,reference_number,status_id,brand_id', 'contentItem.status:id,name,slug'])
            ->whereBetween('post_on', [$week['start'], $week['end']])
            ->orderBy('post_on')
            ->orderBy('id')
            ->get();

        $options = ContentItem::query()
            ->visibleTo($user)
            ->whereHas('status', fn ($query) => $query->where('slug', '!=', 'archived'))
            ->with('status:id,name')
            ->orderBy('title')
            ->get(['id', 'title', 'reference_number', 'status_id']);

        $accessiblePageIds = $user->accessibleSocialAccountIds();

        $facebookPosts = SocialPost::query()
            ->with(['account:id,name,external_id', 'user:id,name'])
            ->whereHas('account', function ($query) use ($user, $accessiblePageIds) {
                $query->whereHas('platform', fn ($platform) => $platform->where('slug', 'facebook'));
                if (! $user->isAdmin()) {
                    $query->whereIn('brand_id', $user->accessibleBrandIds() ?: [0])
                        ->whereIn('id', $accessiblePageIds ?: [0]);
                }
            })
            ->where(function ($query) use ($week) {
                $query->where(function ($scheduled) use ($week) {
                    $scheduled->where('status', 'scheduled')
                        ->whereNotNull('scheduled_publish_at')
                        ->whereDate('scheduled_publish_at', '>=', $week['start'])
                        ->whereDate('scheduled_publish_at', '<=', $week['end']);
                })->orWhere(function ($published) use ($week) {
                    $published->where('status', 'published')
                        ->whereNotNull('published_at')
                        ->whereDate('published_at', '>=', $week['start'])
                        ->whereDate('published_at', '<=', $week['end']);
                });
            })
            ->orderByRaw('COALESCE(scheduled_publish_at, published_at) asc')
            ->limit(200)
            ->get()
            ->map(function (SocialPost $post) {
                $at = $post->scheduled_publish_at ?? $post->published_at;
                if (! $at) {
                    return null;
                }

                return [
                    'id' => $post->id,
                    'weekday' => PostingScheduleSlots::weekdayKey($at),
                    'slot' => PostingScheduleSlots::resolveFromDateTime($at),
                    'date' => $at->toDateString(),
                    'at' => $at->toIso8601String(),
                    'caption' => Str::limit((string) $post->caption, 90),
                    'media_type' => $post->media_type,
                    'media_name' => $post->media_name,
                    'status' => $post->status,
                    'permalink_url' => $post->permalink_url,
                    'page' => $post->account?->name,
                    'author' => $post->user?->name,
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('Schedule/Posting', [
            'canAssign' => $this->canAssign($user),
            'canPost' => $user->hasPermission('content.publish'),
            'week' => [
                'start' => $week['start'],
                'end' => $week['end'],
                'label' => $week['label'],
                'prev' => $week['prev'],
                'next' => $week['next'],
                'is_current' => $week['is_current'],
                'days' => $week['days'],
            ],
            'entries' => $entries->map(fn (PostingScheduleEntry $entry) => [
                'id' => $entry->id,
                'weekday' => $entry->weekday,
                'slot' => $entry->slot,
                'post_on' => $entry->post_on?->toDateString(),
                'content' => [
                    'id' => $entry->contentItem?->id,
                    'title' => $entry->contentItem?->title ?? 'Removed content',
                    'reference_number' => $entry->contentItem?->reference_number,
                    'status' => $entry->contentItem?->status?->name,
                    'href' => $entry->contentItem && $user->can('view', $entry->contentItem)
                        ? '/content/'.$entry->contentItem->id
                        : null,
                ],
            ])->values(),
            'facebookPosts' => $facebookPosts,
            'contentOptions' => $options->map(fn (ContentItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'reference_number' => $item->reference_number,
                'status' => $item->status?->name,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ContentItem::class);
        abort_unless($this->canAssign($request->user()), 403);

        $data = $request->validate([
            'weekday' => ['required', Rule::in(array_keys(self::WEEKDAYS))],
            'slot' => ['required', Rule::in(self::SLOTS)],
            'post_on' => ['required', 'date_format:Y-m-d'],
            'content_item_id' => ['required', 'integer'],
        ]);

        $postOn = Carbon::createFromFormat('Y-m-d', $data['post_on'])->startOfDay();
        if ($postOn->dayOfWeekIso !== self::WEEKDAYS[$data['weekday']]) {
            throw ValidationException::withMessages([
                'post_on' => 'Choose a '.ucfirst($data['weekday']).' for this slot.',
            ]);
        }

        $content = ContentItem::query()
            ->visibleTo($request->user())
            ->whereKey($data['content_item_id'])
            ->whereHas('status', fn ($query) => $query->where('slug', '!=', 'archived'))
            ->first();

        if ($content === null) {
            throw ValidationException::withMessages([
                'content_item_id' => 'Choose a content item you can add to this schedule.',
            ]);
        }

        PostingScheduleEntry::query()->firstOrCreate([
            'weekday' => $data['weekday'],
            'slot' => $data['slot'],
            'post_on' => $postOn->toDateString(),
            'content_item_id' => $content->id,
        ], [
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Content added to the schedule.');
    }

    public function destroy(Request $request, PostingScheduleEntry $entry): RedirectResponse
    {
        $this->authorize('viewAny', ContentItem::class);
        abort_unless($this->canAssign($request->user()), 403);

        $entry->delete();

        return back()->with('success', 'Content removed from the schedule.');
    }

    private function canAssign(User $user): bool
    {
        return $user->hasPermission('content.update')
            || $user->hasPermission('content.update.any')
            || $user->hasPermission('content.assign');
    }

    /**
     * @return array{
     *     timezone: string,
     *     start: string,
     *     end: string,
     *     label: string,
     *     prev: string,
     *     next: string,
     *     is_current: bool,
     *     days: list<array{key: string, name: string, date: string, label: string, is_today: bool}>
     * }
     */
    private function resolveWeek(Request $request): array
    {
        $timezone = 'Asia/Manila';
        $today = Carbon::now($timezone)->startOfDay();

        try {
            $anchor = $request->filled('week')
                ? Carbon::createFromFormat('Y-m-d', (string) $request->input('week'), $timezone)->startOfDay()
                : $today->copy();
        } catch (\Throwable) {
            $anchor = $today->copy();
        }

        $weekStart = $anchor->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);
        $currentStart = $today->copy()->startOfWeek(Carbon::MONDAY);

        $days = [];
        foreach (array_keys(self::WEEKDAYS) as $index => $key) {
            $date = $weekStart->copy()->addDays($index);
            $days[] = [
                'key' => $key,
                'name' => ucfirst($key),
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'is_today' => $date->isSameDay($today),
            ];
        }

        return [
            'timezone' => $timezone,
            'start' => $weekStart->toDateString(),
            'end' => $weekEnd->toDateString(),
            'label' => $weekStart->format('M j').' – '.$weekEnd->format('M j, Y'),
            'prev' => $weekStart->copy()->subWeek()->toDateString(),
            'next' => $weekStart->copy()->addWeek()->toDateString(),
            'is_current' => $weekStart->equalTo($currentStart),
            'days' => $days,
        ];
    }
}
