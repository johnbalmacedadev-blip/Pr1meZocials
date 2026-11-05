<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One name in the exchange gift draw. Once someone confirms it, nobody else can draw it.
 */
class RolletSlip extends Model
{
    protected $fillable = ['name', 'picked_by', 'picked_at', 'draw_token', 'held_until'];

    protected function casts(): array
    {
        return [
            'picked_at' => 'datetime',
            'held_until' => 'datetime',
        ];
    }

    /** Not confirmed by anyone, and not being held for someone who is about to confirm. */
    public function scopeFree(Builder $query): Builder
    {
        return $query->whereNull('picked_at')->where(function (Builder $hold) {
            $hold->whereNull('held_until')->orWhere('held_until', '<', now());
        });
    }
}
