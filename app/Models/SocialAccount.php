<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    protected $fillable = [
        'brand_id',
        'platform_id',
        'name',
        'handle',
        'external_id',
        'access_token',
        'token_expires_at',
        'meta',
        'profile_url',
        'is_active',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'meta' => 'array',
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

    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function isFacebookReady(): bool
    {
        return $this->is_active
            && filled($this->external_id)
            && filled($this->access_token);
    }
}
