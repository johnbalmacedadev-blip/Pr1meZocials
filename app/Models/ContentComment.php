<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'content_item_id',
        'user_id',
        'parent_id',
        'body',
        'mention_ids',
    ];

    protected function casts(): array
    {
        return [
            'mention_ids' => 'array',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
