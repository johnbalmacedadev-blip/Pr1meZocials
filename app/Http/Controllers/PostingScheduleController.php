<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\PostingScheduleEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    private const SLOTS = ['morning', 'afternoon', 'flex', 'evening'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ContentItem::class);

        $entries = PostingScheduleEntry::query()
            ->with(['contentItem:id,title,reference_number,status_id,brand_id', 'contentItem.status:id,name,slug'])
            ->orderBy('post_on')
            ->orderBy('id')
            ->get();

        $options = ContentItem::query()
            ->visibleTo($request->user())
            ->whereHas('status', fn ($query) => $query->where('slug', '!=', 'archived'))
            ->with('status:id,name')
            ->orderBy('title')
            ->get(['id', 'title', 'reference_number', 'status_id']);

        return Inertia::render('Schedule/Posting', [
            'canAssign' => $this->canAssign($request->user()),
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
                    'href' => $entry->contentItem && $request->user()->can('view', $entry->contentItem)
                        ? '/content/'.$entry->contentItem->id
                        : null,
                ],
            ])->values(),
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
}
