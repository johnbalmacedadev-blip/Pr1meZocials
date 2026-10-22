<?php

namespace App\Enums;

enum Distribution: string
{
    case Organic = 'organic';
    case Paid = 'paid';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Organic => 'Organic',
            self::Paid => 'Paid',
            self::Both => 'Organic and paid',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
