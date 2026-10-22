<?php

namespace App\Services;

use App\Models\ContentItem;

class ReferenceNumberGenerator
{
    /**
     * Call this inside the same database transaction as the insert.
     * The unique index on reference_number is the final guard against races.
     */
    public function next(): string
    {
        $prefix = 'CNT-'.now()->year.'-';

        $latest = ContentItem::withTrashed()
            ->where('reference_number', 'like', $prefix.'%')
            ->orderByDesc('reference_number')
            ->lockForUpdate()
            ->value('reference_number');

        $sequence = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
