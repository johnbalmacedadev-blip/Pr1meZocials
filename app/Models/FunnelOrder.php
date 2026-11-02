<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelOrder extends Model
{
    public const STATUSES = ['new', 'confirmed', 'shipped', 'delivered', 'cancelled'];

    protected $fillable = [
        'funnel_id',
        'full_name',
        'phone',
        'email',
        'address',
        'province',
        'city',
        'barangay',
        'quantity',
        'unit_price',
        'total',
        'status',
        'source',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'source' => 'array',
        ];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function reference(): string
    {
        return 'FO-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function shippingAddress(): string
    {
        return collect([$this->address, $this->barangay, $this->city, $this->province])->filter()->implode(', ');
    }
}
