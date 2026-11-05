<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One person in the exchange gift draw, and the name others can draw for them.
 * Once someone confirms it, nobody else can draw it.
 */
class RolletSlip extends Model
{
    protected $fillable = ['name', 'picked_by', 'picked_at', 'draw_token', 'held_until', 'picker_slip_id'];

    protected function casts(): array
    {
        return [
            'picked_at' => 'datetime',
            'held_until' => 'datetime',
            'picker_slip_id' => 'integer',
        ];
    }

    /** Not confirmed by anyone, and not being held for someone who is about to confirm. */
    public function scopeFree(Builder $query): Builder
    {
        return $query->whereNull('picked_at')->where(function (Builder $hold) {
            $hold->whereNull('held_until')->orWhere('held_until', '<', now());
        });
    }

    /**
     * Display names by slip id. People who share a name are numbered: Kyle 1, Kyle 2.
     *
     * @return array<int, string>
     */
    public static function labels(): array
    {
        $slips = self::query()->orderBy('id')->get(['id', 'name']);
        $totals = $slips->countBy('name');
        $seen = [];
        $labels = [];

        foreach ($slips as $slip) {
            $seen[$slip->name] = ($seen[$slip->name] ?? 0) + 1;
            $labels[$slip->id] = $totals[$slip->name] > 1 ? $slip->name.' '.$seen[$slip->name] : $slip->name;
        }

        return $labels;
    }

    /**
     * People who have confirmed a pick, or are holding one right now.
     *
     * @return list<int>
     */
    public static function busyPickerIds(): array
    {
        return self::query()
            ->whereNotNull('picker_slip_id')
            ->where(fn (Builder $query) => $query->whereNotNull('picked_at')->orWhere('held_until', '>=', now()))
            ->pluck('picker_slip_id')
            ->all();
    }
}
