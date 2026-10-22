<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStatus extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
        'sort_order',
        'is_system',
        'enter_permission',
        'allowed_transitions',
        'required_fields',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'allowed_transitions' => 'array',
            'required_fields' => 'array',
        ];
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'status_id');
    }
}
