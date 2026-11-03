<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The team works in Philippine time, while the database keeps UTC. Use this
 * whenever a date is typed by a person or shown on a calendar.
 */
class PhilippineTime
{
    public const ZONE = 'Asia/Manila';

    public static function now(): Carbon
    {
        return Carbon::now(self::ZONE);
    }

    /**
     * A date and time typed by a person, ready to store as UTC.
     */
    public static function parse(string $value): Carbon
    {
        return Carbon::parse($value, self::ZONE)->utc();
    }

    public static function of(CarbonInterface $moment): Carbon
    {
        return Carbon::instance($moment)->setTimezone(self::ZONE);
    }

    public static function startOfDayUtc(string $date): Carbon
    {
        return Carbon::parse($date, self::ZONE)->startOfDay()->utc();
    }

    public static function endOfDayUtc(string $date): Carbon
    {
        return Carbon::parse($date, self::ZONE)->endOfDay()->utc();
    }
}
