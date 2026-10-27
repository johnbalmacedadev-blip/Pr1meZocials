<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EventOccurrence extends Model
{
    protected $fillable = [
        'event_id',
        'occurs_on',
        'start_time',
        'end_time',
        'title_override',
        'notes',
        'poster_path',
        'poster_name',
        'video_path',
        'video_name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'occurs_on' => 'date',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function displayTitle(): string
    {
        return $this->title_override ?: ($this->event?->title ?? 'Event');
    }

    public function hasCustomMedia(): bool
    {
        return filled($this->poster_path) || filled($this->video_path);
    }

    public function effectivePosterPath(): ?string
    {
        return $this->poster_path ?: $this->event?->default_poster_path;
    }

    public function effectiveVideoPath(): ?string
    {
        return $this->video_path ?: $this->event?->default_video_path;
    }

    public function deleteStoredFiles(): void
    {
        foreach ([$this->poster_path, $this->video_path] as $path) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
