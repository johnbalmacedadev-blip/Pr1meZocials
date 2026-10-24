<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class PostingScheduleSlots
{
    /** @var list<string> */
    public const KEYS = ['morning', 'afternoon', 'flex', 'evening'];

    /**
     * Default Facebook publish time for each schedule column (local clock).
     *
     * @var array<string, string>
     */
    public const DEFAULT_TIMES = [
        'morning' => '08:00',
        'afternoon' => '12:00',
        'flex' => '16:00',
        'evening' => '19:00',
    ];

    public static function defaultTime(string $slot): string
    {
        return self::DEFAULT_TIMES[$slot] ?? '12:00';
    }

    public static function resolveFromDateTime(Carbon $at): string
    {
        $hour = (int) $at->format('G');

        if ($hour >= 7 && $hour < 11) {
            return 'morning';
        }

        if ($hour >= 11 && $hour < 15) {
            return 'afternoon';
        }

        if ($hour >= 15 && $hour < 19) {
            return 'flex';
        }

        return 'evening';
    }

    public static function weekdayKey(Carbon $at): string
    {
        return strtolower($at->englishDayOfWeek);
    }
}
