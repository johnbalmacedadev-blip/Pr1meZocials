<?php

namespace App\Enums;

enum ContentFormat: string
{
    case Video = 'video';
    case Photo = 'photo';
    case Carousel = 'carousel';
    case Reel = 'reel';
    case Story = 'story';
    case Livestream = 'livestream';
    case Text = 'text';
    case Infographic = 'infographic';
    case Ad = 'ad';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Photo => 'Photo / Static Image',
            self::Carousel => 'Carousel',
            self::Reel => 'Reel / Short-form Video',
            self::Story => 'Story',
            self::Livestream => 'Livestream',
            self::Text => 'Text Post',
            self::Infographic => 'Infographic',
            self::Ad => 'Advertisement Creative',
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
