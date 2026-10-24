<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionPreset extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'matrix',
        'extras',
        'is_system',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'matrix' => 'array',
            'extras' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
