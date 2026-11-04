<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class PostingScheduleSlots
{
    /** @var list<string> */
    public const KEYS = ['morning', 'company_am', 'afternoon', 'company_pm', 'flex', 'evening', 'company_night'];

    /**
     * Default Facebook publish time for each schedule column (local clock).
     *
     * @var array<string, string>
     */
    public const DEFAULT_TIMES = [
        'morning' => '08:00',
        'company_am' => '10:00',
        'afternoon' => '12:00',
        'company_pm' => '14:00',
        'flex' => '16:00',
        'evening' => '19:00',
        'company_night' => '21:00',
    ];

    /** @var array<string, string> */
    private const LABELS = [
        'morning' => 'Morning',
        'company_am' => 'Company 10–11 AM',
        'afternoon' => 'Afternoon',
        'company_pm' => 'Company 2–3 PM',
        'flex' => 'Flex slot',
        'evening' => 'Evening',
        'company_night' => 'Company 9 PM onwards',
    ];

    public static function defaultTime(string $slot): string
    {
        return self::DEFAULT_TIMES[$slot] ?? '12:00';
    }

    public static function label(string $slot): string
    {
        return self::LABELS[$slot] ?? ucfirst($slot);
    }

    public static function resolveFromDateTime(Carbon $at): string
    {
        return match ((int) $at->format('G')) {
            7, 8, 9 => 'morning',
            10 => 'company_am',
            11, 12, 13 => 'afternoon',
            14 => 'company_pm',
            15, 16, 17, 18 => 'flex',
            19, 20 => 'evening',
            // 9 PM onwards, through the night until the morning slot starts.
            default => 'company_night',
        };
    }

    public static function weekdayKey(Carbon $at): string
    {
        return strtolower($at->englishDayOfWeek);
    }
}
