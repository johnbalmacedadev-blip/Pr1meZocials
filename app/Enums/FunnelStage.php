<?php

namespace App\Enums;

enum FunnelStage: string
{
    case Awareness = 'awareness';
    case Consideration = 'consideration';
    case Conversion = 'conversion';
    case Retention = 'retention';

    public function label(): string
    {
        return match ($this) {
            self::Awareness => 'Awareness',
            self::Consideration => 'Consideration',
            self::Conversion => 'Conversion',
            self::Retention => 'Retention',
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
