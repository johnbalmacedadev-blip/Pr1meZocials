<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostingScheduleEntry extends Model
{
    protected $fillable = [
        'weekday',
        'slot',
        'post_on',
        'content_item_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'post_on' => 'date',
        ];
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
