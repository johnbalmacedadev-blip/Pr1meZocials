<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class EventOccurrenceGenerator
{
    /**
     * Create missing occurrence rows for an event series.
     * Does not overwrite rows that already have custom media, notes, or overrides.
     */
    public function sync(Event $event): void
    {
        $dates = $this->datesFor($event);
        $existing = $event->occurrences()->get()->keyBy(fn (EventOccurrence $row) => $row->occurs_on->toDateString());

        foreach ($dates as $date) {
            $key = $date->toDateString();
            if ($existing->has($key)) {
                continue;
            }

            $event->occurrences()->create([
                'occurs_on' => $key,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'status' => 'scheduled',
            ]);
        }

        // Remove future empty occurrences that fall outside the new rule.
        $keep = collect($dates)->map->toDateString()->all();
        $event->occurrences()
            ->where('occurs_on', '>=', now()->toDateString())
            ->whereNotIn('occurs_on', $keep)
            ->get()
            ->each(function (EventOccurrence $occurrence) {
                if ($occurrence->hasCustomMedia() || filled($occurrence->title_override) || filled($occurrence->notes)) {
                    return;
                }
                $occurrence->delete();
            });
    }

    /**
     * @return list<Carbon>
     */
    public function datesFor(Event $event): array
    {
        $start = Carbon::parse($event->starts_on)->startOfDay();
        $end = $event->ends_on
            ? Carbon::parse($event->ends_on)->startOfDay()
            : $start->copy()->addMonths(6);

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        if ($event->recurrence !== 'weekly') {
            return [$start];
        }

        $weekdays = collect($event->recurrence_weekdays ?? [])
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => $day >= 1 && $day <= 7)
            ->unique()
            ->values()
            ->all();

        if ($weekdays === []) {
            return [$start];
        }

        $dates = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            /** @var Carbon $day */
            if (in_array((int) $day->isoWeekday(), $weekdays, true)) {
                $dates[] = $day->copy()->startOfDay();
            }
        }

        return $dates;
    }
}
