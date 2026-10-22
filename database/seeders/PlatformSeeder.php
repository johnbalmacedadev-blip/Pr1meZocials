<?php

namespace Database\Seeders;

use App\Models\Platform;
use App\Support\Lookups;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            ['Facebook', 'facebook', 'sky'],
            ['Instagram', 'instagram', 'rose'],
            ['TikTok', 'tiktok', 'zinc'],
            ['YouTube', 'youtube', 'red'],
            ['LinkedIn', 'linkedin', 'indigo'],
            ['X', 'x', 'slate'],
            ['Pinterest', 'pinterest', 'orange'],
        ];

        foreach ($platforms as $index => [$name, $slug, $color]) {
            Platform::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'color' => $color,
                'is_custom' => false,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }

        Lookups::flush();
    }
}
