<?php

namespace Database\Seeders;

use App\Models\ContentObjective;
use App\Models\ContentPillar;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $pillars = [
            ['Education', 'education', 'Teach the audience something useful.'],
            ['Product Showcase', 'product-showcase', 'Show the product in use.'],
            ['Entertainment', 'entertainment', 'Earn attention with personality.'],
            ['Testimonials', 'testimonials', 'Let customers tell the story.'],
            ['Community', 'community', 'Highlight the people around the brand.'],
            ['Brand Awareness', 'brand-awareness', 'Make the brand recognizable.'],
            ['Recruitment', 'recruitment', 'Attract future teammates.'],
            ['Promotional Content', 'promotional', 'Support an offer or launch.'],
        ];

        foreach ($pillars as [$name, $slug, $description]) {
            ContentPillar::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'is_active' => true,
            ]);
        }

        $objectives = [
            ['Reach', 'reach', 'Increase the number of people who see the content.'],
            ['Engagement', 'engagement', 'Earn reactions, comments, shares, or saves.'],
            ['Leads', 'leads', 'Collect contact or inquiry intent.'],
            ['Sales', 'sales', 'Drive a purchase or booked order.'],
            ['Community Growth', 'community-growth', 'Gain followers or members.'],
            ['Retention', 'retention', 'Bring existing customers back.'],
        ];

        foreach ($objectives as [$name, $slug, $description]) {
            ContentObjective::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'is_active' => true,
            ]);
        }

        Setting::putValue('organization_name', 'Prime Z');
        Setting::putValue('deadline_warning_days', 2);
    }
}
