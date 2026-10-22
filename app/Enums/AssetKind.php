<?php

namespace App\Enums;

enum AssetKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Script = 'script';
    case Reference = 'reference';
    case Logo = 'logo';
    case Guideline = 'guideline';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Image',
            self::Video => 'Video draft',
            self::Script => 'Script or copy',
            self::Reference => 'Reference',
            self::Logo => 'Brand logo',
            self::Guideline => 'Brand guideline',
            self::Other => 'Other',
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
