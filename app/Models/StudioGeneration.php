<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudioGeneration extends Model
{
    protected $fillable = [
        'brand_id', 'user_id', 'parent_id', 'prompt', 'aspect_ratio', 'image_size', 'model',
        'reference_keys', 'path', 'mime_type', 'size', 'width', 'height',
    ];

    protected function casts(): array
    {
        return ['reference_keys' => 'array'];
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
