<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspirationItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'platform_id',
        'user_id',
        'name',
        'url',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
