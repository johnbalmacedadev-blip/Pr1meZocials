<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Services\EventOccurrenceGenerator;
use App\Support\FormOptions;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Event::class);

        $brandIds = $request->user()->accessibleBrandIds() ?: [0];
        $anchor = Carbon::parse($request->string('date')->toString() ?: now()->toDateString())->startOfDay();
        $viewStart = $anchor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $viewEnd = $anchor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $occurrences = EventOccurrence::query()
            ->with(['event.brand:id,name'])
            ->whereBetween('occurs_on', [$viewStart->toDateString(), $viewEnd->toDateString()])
            ->whereHas('event', function ($query) use ($brandIds, $request) {
                $query->whereIn('brand_id', $brandIds)->whereNull('deleted_at');
                if ($request->filled('brand_id')) {
                    $query->where('brand_id', $request->integer('brand_id'));
                }
            })
            ->orderBy('occurs_on')
            ->orderBy('start_time')
            ->get()
            ->map(fn (EventOccurrence $occurrence) => $this->occurrencePayload($occurrence));

        $series = Event::query()
            ->whereIn('brand_id', $brandIds)
            ->with(['brand:id,name'])
            ->withCount(['occurrences' => fn ($query) => $query->where('occurs_on', '>=', now()->toDateString())])
            ->when($request->filled('brand_id'), fn ($query) => $query->where('brand_id', $request->integer('brand_id')))
            ->orderBy('title')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'brand' => $event->brand?->only(['id', 'name']),
                'recurrence_label' => $event->recurrenceLabel(),
                'starts_on' => $event->starts_on?->toDateString(),
                'ends_on' => $event->ends_on?->toDateString(),
                'upcoming_count' => $event->occurrences_count,
                'status' => $event->status,
            ]);

        return Inertia::render('Events/Index', [
            'date' => $anchor->toDateString(),
            'occurrences' => $occurrences,
            'series' => $series,
            'filters' => $request->only(['brand_id']),
            'options' => FormOptions::for($request->user()),
            'canCreate' => $request->user()->can('create', Event::class),
            'canManage' => $request->user()->hasPermission('events.update'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Event::class);

        return Inertia::render('Events/Form', [
            'event' => null,
            'options' => FormOptions::for($request->user()),
        ]);
    }

    public function store(Request $request, EventOccurrenceGenerator $generator): RedirectResponse
    {
        $this->authorize('create', Event::class);
        $data = $this->validated($request);
        abort_unless($request->user()->canAccessBrand((int) $data['brand_id']), 403);

        $paths = $this->storeDefaultMedia($request);

        $event = Event::query()->create([
            ...$data,
            ...$paths,
            'created_by' => $request->user()->id,
            'status' => 'active',
        ]);

        $generator->sync($event);

        return redirect()->route('events.show', $event)->with('success', 'Event created. Upload a poster or video invitation on each date as needed.');
    }

    public function show(Request $request, Event $event): Response
    {
        $this->authorize('view', $event);

        $event->load(['brand:id,name', 'creator:id,name', 'occurrences']);

        return Inertia::render('Events/Show', [
            'event' => $this->eventPayload($event),
            'occurrences' => $event->occurrences->map(fn (EventOccurrence $occurrence) => $this->occurrencePayload($occurrence, includeSeriesDefaults: true))->values(),
            'canManage' => $request->user()->can('update', $event),
        ]);
    }

    public function edit(Request $request, Event $event): Response
    {
        $this->authorize('update', $event);

        return Inertia::render('Events/Form', [
            'event' => $this->eventPayload($event),
            'options' => FormOptions::for($request->user()),
        ]);
    }

    public function update(Request $request, Event $event, EventOccurrenceGenerator $generator): RedirectResponse
    {
        $this->authorize('update', $event);
        $data = $this->validated($request);
        abort_unless($request->user()->canAccessBrand((int) $data['brand_id']), 403);

        $paths = $this->storeDefaultMedia($request, $event);
        $event->fill([...$data, ...$paths]);
        $event->save();
        $generator->sync($event->fresh());

        return redirect()->route('events.show', $event)->with('success', 'Event updated. Upcoming dates were refreshed.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $event->occurrences->each(function (EventOccurrence $occurrence) {
            $occurrence->deleteStoredFiles();
        });

        foreach ([$event->default_poster_path, $event->default_video_path] as $path) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        $event->delete();

        return redirect()->route('events.index')->with('success', 'Event removed.');
    }

    public function updateOccurrence(Request $request, EventOccurrence $occurrence): RedirectResponse
    {
        $occurrence->loadMissing('event');
        $this->authorize('update', $occurrence->event);

        $data = $request->validate([
            'title_override' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::in(['scheduled', 'cancelled', 'completed'])],
            'poster' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:102400'],
            'remove_poster' => ['nullable', 'boolean'],
            'remove_video' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['remove_poster']) && $occurrence->poster_path) {
            Storage::disk('local')->delete($occurrence->poster_path);
            $occurrence->poster_path = null;
            $occurrence->poster_name = null;
        }

        if (! empty($data['remove_video']) && $occurrence->video_path) {
            Storage::disk('local')->delete($occurrence->video_path);
            $occurrence->video_path = null;
            $occurrence->video_name = null;
        }

        if ($request->hasFile('poster')) {
            if ($occurrence->poster_path) {
                Storage::disk('local')->delete($occurrence->poster_path);
            }
            $file = $request->file('poster');
            $occurrence->poster_path = $file->store('events/'.$occurrence->event_id.'/occurrences', 'local');
            $occurrence->poster_name = $file->getClientOriginalName();
        }

        if ($request->hasFile('video')) {
            if ($occurrence->video_path) {
                Storage::disk('local')->delete($occurrence->video_path);
            }
            $file = $request->file('video');
            $occurrence->video_path = $file->store('events/'.$occurrence->event_id.'/occurrences', 'local');
            $occurrence->video_name = $file->getClientOriginalName();
        }

        $occurrence->fill([
            'title_override' => $data['title_override'] ?: null,
            'notes' => $data['notes'] ?: null,
            'start_time' => $data['start_time'] ?: null,
            'end_time' => $data['end_time'] ?: null,
            'status' => $data['status'],
        ]);
        $occurrence->save();

        return back()->with('success', 'Schedule updated for '.$occurrence->occurs_on->toFormattedDateString().'.');
    }

    public function media(Request $request, EventOccurrence $occurrence, string $type): StreamedResponse|HttpResponse
    {
        $occurrence->loadMissing('event');
        $this->authorize('view', $occurrence->event);

        $path = $type === 'video'
            ? $occurrence->effectiveVideoPath()
            : $occurrence->effectivePosterPath();

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'recurrence' => ['required', Rule::in(['none', 'weekly'])],
            'recurrence_weekdays' => ['nullable', 'array'],
            'recurrence_weekdays.*' => ['integer', 'between:1,7'],
            'default_poster' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'],
            'default_video' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:102400'],
        ]);

        if ($data['recurrence'] === 'weekly') {
            $weekdays = array_values(array_unique(array_map('intval', $data['recurrence_weekdays'] ?? [])));
            if ($weekdays === []) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'recurrence_weekdays' => 'Pick at least one weekday for a repeating event.',
                ]);
            }
            $data['recurrence_weekdays'] = $weekdays;
        } else {
            $data['recurrence_weekdays'] = null;
        }

        foreach (['description', 'location', 'ends_on', 'start_time', 'end_time'] as $field) {
            if (($data[$field] ?? null) === '') {
                $data[$field] = null;
            }
        }

        unset($data['default_poster'], $data['default_video']);

        return $data;
    }

    /**
     * @return array<string, string|null>
     */
    private function storeDefaultMedia(Request $request, ?Event $event = null): array
    {
        $paths = [];

        if ($request->hasFile('default_poster')) {
            if ($event?->default_poster_path) {
                Storage::disk('local')->delete($event->default_poster_path);
            }
            $file = $request->file('default_poster');
            $paths['default_poster_path'] = $file->store('events/defaults', 'local');
            $paths['default_poster_name'] = $file->getClientOriginalName();
        }

        if ($request->hasFile('default_video')) {
            if ($event?->default_video_path) {
                Storage::disk('local')->delete($event->default_video_path);
            }
            $file = $request->file('default_video');
            $paths['default_video_path'] = $file->store('events/defaults', 'local');
            $paths['default_video_name'] = $file->getClientOriginalName();
        }

        return $paths;
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(Event $event): array
    {
        $event->loadMissing(['brand:id,name', 'creator:id,name']);

        return [
            'id' => $event->id,
            'brand_id' => $event->brand_id,
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'starts_on' => $event->starts_on?->toDateString(),
            'ends_on' => $event->ends_on?->toDateString(),
            'start_time' => $event->start_time ? substr((string) $event->start_time, 0, 5) : null,
            'end_time' => $event->end_time ? substr((string) $event->end_time, 0, 5) : null,
            'recurrence' => $event->recurrence,
            'recurrence_weekdays' => $event->recurrence_weekdays ?? [],
            'recurrence_label' => $event->recurrenceLabel(),
            'default_poster_name' => $event->default_poster_name,
            'default_video_name' => $event->default_video_name,
            'has_default_poster' => filled($event->default_poster_path),
            'has_default_video' => filled($event->default_video_path),
            'status' => $event->status,
            'brand' => $event->brand?->only(['id', 'name']),
            'creator' => $event->creator?->only(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function occurrencePayload(EventOccurrence $occurrence, bool $includeSeriesDefaults = false): array
    {
        $occurrence->loadMissing('event.brand:id,name');

        return [
            'id' => $occurrence->id,
            'event_id' => $occurrence->event_id,
            'occurs_on' => $occurrence->occurs_on?->toDateString(),
            'start_time' => $occurrence->start_time ? substr((string) $occurrence->start_time, 0, 5) : null,
            'end_time' => $occurrence->end_time ? substr((string) $occurrence->end_time, 0, 5) : null,
            'title' => $occurrence->displayTitle(),
            'title_override' => $occurrence->title_override,
            'notes' => $occurrence->notes,
            'status' => $occurrence->status,
            'poster_name' => $occurrence->poster_name,
            'video_name' => $occurrence->video_name,
            'has_poster' => filled($occurrence->effectivePosterPath()),
            'has_video' => filled($occurrence->effectiveVideoPath()),
            'has_custom_poster' => filled($occurrence->poster_path),
            'has_custom_video' => filled($occurrence->video_path),
            'using_default_poster' => ! filled($occurrence->poster_path) && filled($occurrence->event?->default_poster_path),
            'using_default_video' => ! filled($occurrence->video_path) && filled($occurrence->event?->default_video_path),
            'poster_url' => filled($occurrence->effectivePosterPath()) ? route('events.occurrences.media', [$occurrence, 'poster']) : null,
            'video_url' => filled($occurrence->effectiveVideoPath()) ? route('events.occurrences.media', [$occurrence, 'video']) : null,
            'brand' => $occurrence->event?->brand?->only(['id', 'name']),
            'event_title' => $occurrence->event?->title,
            'include_series_defaults' => $includeSeriesDefaults,
        ];
    }
}
