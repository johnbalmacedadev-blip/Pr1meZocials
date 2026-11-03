<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An image made in Google Flow and brought back to Studio, with the prompt and references behind it.
 */
class StudioGeneration extends Model
{
    protected $fillable = [
        'brand_id', 'user_id', 'parent_id', 'prompt', 'aspect_ratio',
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
