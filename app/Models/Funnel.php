<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Funnel extends Model
{
    use SoftDeletes;

    public const STATUSES = ['active', 'draft', 'paused'];

    protected $fillable = [
        'brand_id',
        'created_by',
        'name',
        'product_name',
        'slug',
        'short_link',
        'template',
        'status',
        'unit_price',
        'compare_at_price',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(FunnelOrder::class);
    }

    public function isLive(): bool
    {
        return $this->status === 'active';
    }

    public function publicUrl(): string
    {
        return route('funnels.public', $this->slug);
    }
}
