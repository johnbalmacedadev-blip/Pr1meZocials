<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'created_by',
        'title',
        'description',
        'location',
        'starts_on',
        'ends_on',
        'start_time',
        'end_time',
        'recurrence',
        'recurrence_weekdays',
        'default_poster_path',
        'default_poster_name',
        'default_video_path',
        'default_video_name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'recurrence_weekdays' => 'array',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(EventOccurrence::class)->orderBy('occurs_on');
    }

    public function recurrenceLabel(): string
    {
        if ($this->recurrence !== 'weekly') {
            return 'One-time';
        }

        $labels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
        $days = collect($this->recurrence_weekdays ?? [])
            ->map(fn ($day) => $labels[(int) $day] ?? null)
            ->filter()
            ->values();

        return $days->isEmpty() ? 'Weekly' : 'Every '.$days->join(', ');
    }
}
