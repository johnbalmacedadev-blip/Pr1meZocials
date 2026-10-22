<?php

namespace Database\Seeders;

use App\Models\AudienceSegment;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\ContentItem;
use App\Models\ContentObjective;
use App\Models\ContentPillar;
use App\Models\Platform;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use App\Models\WorkflowStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'slug');

        $people = [
            ['Avery Chen', 'avery@primez.test', 'Operations Lead', 'administrator'],
            ['Jordan Blake', 'jordan@primez.test', 'Marketing Manager', 'marketing_manager'],
            ['Sam Patel', 'sam@primez.test', 'Social Media Manager', 'social_media_manager'],
            ['Riley Nguyen', 'riley@primez.test', 'Graphic Designer', 'creative'],
            ['Casey Brooks', 'casey@primez.test', 'Video Editor', 'creative'],
            ['Morgan Diaz', 'morgan@primez.test', 'Copywriter', 'creative'],
            ['Quinn Alvarez', 'quinn@primez.test', 'Creative Reviewer', 'reviewer'],
        ];

        $users = [];

        foreach ($people as [$name, $email, $title, $role]) {
            $user = User::query()->updateOrCreate(['email' => $email], [
                'name' => $name,
                'job_title' => $title,
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->roles()->sync([$roles[$role]]);
            $users[$email] = $user;
        }

        $northwind = Brand::query()->updateOrCreate(['slug' => 'northwind-coffee'], [
            'name' => 'Northwind Coffee',
            'description' => 'Specialty coffee roasting and neighborhood cafes.',
            'website' => 'https://example.com/northwind',
            'industry' => 'Food and beverage',
            'primary_color' => 'teal',
            'is_active' => true,
        ]);

        $harbor = Brand::query()->updateOrCreate(['slug' => 'harbor-and-co'], [
            'name' => 'Harbor & Co.',
            'description' => 'Outdoor apparel built for wet trails.',
            'website' => 'https://example.com/harbor',
            'industry' => 'Apparel',
            'primary_color' => 'indigo',
            'is_active' => true,
        ]);

        foreach ($users as $user) {
            $user->brands()->sync([$northwind->id, $harbor->id]);
        }

        $summer = $this->campaign($northwind, $users['jordan@primez.test'], 'Summer Cold Brew Push', 'active', now()->subWeeks(3), now()->addWeeks(5));
        $loyalty = $this->campaign($northwind, $users['sam@primez.test'], 'Loyalty Week', 'planning', now()->addWeeks(2), now()->addWeeks(4));
        $trail = $this->campaign($harbor, $users['jordan@primez.test'], 'Trail Season Launch', 'active', now()->subWeeks(2), now()->addMonth());

        foreach ([$summer, $loyalty, $trail] as $campaign) {
            $campaign->members()->sync([
                $users['sam@primez.test']->id,
                $users['riley@primez.test']->id,
                $users['casey@primez.test']->id,
                $users['morgan@primez.test']->id,
                $users['quinn@primez.test']->id,
            ]);
        }

        $regulars = AudienceSegment::query()->updateOrCreate(
            ['brand_id' => $northwind->id, 'name' => 'Weekday regulars'],
            ['description' => 'People who buy coffee before 9 a.m.', 'is_active' => true],
        );
        $hikers = AudienceSegment::query()->updateOrCreate(
            ['brand_id' => $harbor->id, 'name' => 'Weekend hikers'],
            ['description' => 'People planning day hikes within driving distance.', 'is_active' => true],
        );

        $seasonal = Tag::query()->updateOrCreate(['brand_id' => $northwind->id, 'slug' => 'seasonal'], ['name' => 'Seasonal']);
        $launch = Tag::query()->updateOrCreate(['brand_id' => $harbor->id, 'slug' => 'launch'], ['name' => 'Launch']);

        $pillars = ContentPillar::query()->pluck('id', 'slug');
        $objectives = ContentObjective::query()->pluck('id', 'slug');
        $statuses = WorkflowStatus::query()->pluck('id', 'slug');
        $platforms = Platform::query()->pluck('id', 'slug');

        $coldBrew = $this->content($users['sam@primez.test'], [
            'brand_id' => $northwind->id,
            'campaign_id' => $summer->id,
            'status_id' => $statuses['published'],
            'title' => 'Why cold brew tastes smoother',
            'description' => 'A short education reel on extraction and bitterness.',
            'format' => 'reel',
            'priority' => 'high',
            'distribution' => 'organic',
            'funnel_stage' => 'awareness',
            'pillar_id' => $pillars['education'],
            'objective_id' => $objectives['engagement'],
            'audience_segment_id' => $regulars->id,
            'owner_id' => $users['casey@primez.test']->id,
            'copywriter_id' => $users['morgan@primez.test']->id,
            'reviewer_id' => $users['quinn@primez.test']->id,
            'deadline' => now()->subDays(3)->toDateString(),
            'published_at' => now()->subDays(4),
            'created_at' => now()->subDays(12),
            'original_idea' => 'Show one bean, two brews, and the taste difference in under 20 seconds.',
            'hook' => 'Cold brew is not just hot coffee that gave up.',
            'caption_draft' => 'Steeped cold for 16 hours. Less bitter. Same caffeine kick.',
            'call_to_action' => 'Try the small cold brew this week.',
            'hypothesis' => 'A taste comparison will earn saves from people who think cold brew is a trend.',
            'success_metric' => 'Engagement rate above 4% on Instagram impressions.',
            'complexity' => 3,
        ], [$seasonal->id]);

        $this->version($coldBrew, $platforms['instagram'], 'reel', now()->subDays(4), 'published', 'https://example.com/northwind/cold-brew-ig', [
            'impressions' => 18400, 'reach' => 12100, 'views' => 9800, 'video_views_3s' => 7600,
            'likes' => 940, 'comments_count' => 46, 'shares' => 38, 'saves' => 210, 'link_clicks' => 320,
        ]);
        $this->version($coldBrew, $platforms['tiktok'], 'reel', now()->subDays(4), 'published', 'https://example.com/northwind/cold-brew-tt', [
            'impressions' => 40200, 'reach' => 31000, 'views' => 36000, 'video_views_3s' => 28000,
            'likes' => 3100, 'comments_count' => 140, 'shares' => 420, 'saves' => 860,
        ]);

        $pour = $this->content($users['jordan@primez.test'], [
            'brand_id' => $northwind->id,
            'campaign_id' => $summer->id,
            'status_id' => $statuses['scheduled'],
            'title' => 'Barista tip: the 30-second pour',
            'format' => 'reel',
            'priority' => 'medium',
            'funnel_stage' => 'consideration',
            'pillar_id' => $pillars['education'],
            'objective_id' => $objectives['reach'],
            'owner_id' => $users['casey@primez.test']->id,
            'reviewer_id' => $users['quinn@primez.test']->id,
            'deadline' => now()->addDays(2)->toDateString(),
            'target_production_date' => now()->addDay()->toDateString(),
            'original_idea' => 'One overhead shot, one sentence, one reason the pour matters.',
            'caption_draft' => 'Slow down the last 30 seconds. The cup tastes sweeter.',
            'hypothesis' => 'A practical tip is more saveable than a product shot.',
            'success_metric' => '500 saves across Instagram and TikTok.',
            'complexity' => 2,
        ], [$seasonal->id]);
        $this->version($pour, $platforms['instagram'], 'reel', now()->addDays(2)->setTime(9, 30), 'scheduled');
        $this->version($pour, $platforms['facebook'], 'reel', now()->addDays(2)->setTime(11, 0), 'scheduled');

        $this->content($users['morgan@primez.test'], [
            'brand_id' => $northwind->id,
            'campaign_id' => $summer->id,
            'status_id' => $statuses['internal_review'],
            'title' => 'Customer story: Maya’s morning ritual',
            'format' => 'photo',
            'priority' => 'medium',
            'funnel_stage' => 'consideration',
            'pillar_id' => $pillars['testimonials'],
            'objective_id' => $objectives['community-growth'],
            'owner_id' => $users['riley@primez.test']->id,
            'copywriter_id' => $users['morgan@primez.test']->id,
            'reviewer_id' => $users['quinn@primez.test']->id,
            'deadline' => now()->addDays(4)->toDateString(),
            'original_idea' => 'A portrait and a three-line quote from a regular.',
            'caption_draft' => 'Maya orders the same cup. The reason changes every season.',
            'creative_concept' => 'Natural light, cup in frame, quote as the first line.',
            'complexity' => 2,
        ]);

        $oat = $this->content($users['sam@primez.test'], [
            'brand_id' => $northwind->id,
            'campaign_id' => $summer->id,
            'status_id' => $statuses['in_progress'],
            'title' => 'Oat milk menu, four ways',
            'format' => 'carousel',
            'priority' => 'high',
            'distribution' => 'organic',
            'funnel_stage' => 'consideration',
            'pillar_id' => $pillars['product-showcase'],
            'objective_id' => $objectives['engagement'],
            'owner_id' => $users['riley@primez.test']->id,
            'deadline' => now()->subDay()->toDateString(),
            'original_idea' => 'Four slides, one drink each, same cup so the differences are obvious.',
            'visual_direction' => 'Warm wood bar, consistent crop, flavor name only.',
            'complexity' => 3,
        ]);
        $oat->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();

        $this->content($users['jordan@primez.test'], [
            'brand_id' => $northwind->id,
            'campaign_id' => $loyalty->id,
            'status_id' => $statuses['inbox'],
            'title' => 'Weekend barista hiring note',
            'format' => 'text',
            'priority' => 'low',
            'funnel_stage' => 'awareness',
            'pillar_id' => $pillars['recruitment'],
            'objective_id' => $objectives['leads'],
            'original_idea' => 'A plain spoken post from the manager, not a job-board graphic.',
            'inspiration_url' => 'https://example.com/inspiration/hiring-posts',
            'inspiration_source' => 'Competitor cafe hiring thread',
        ]);

        $this->content($users['sam@primez.test'], [
            'brand_id' => $northwind->id,
            'campaign_id' => $loyalty->id,
            'status_id' => $statuses['ideation'],
            'title' => 'Loyalty week teaser stories',
            'format' => 'story',
            'priority' => 'medium',
            'funnel_stage' => 'retention',
            'pillar_id' => $pillars['promotional'],
            'objective_id' => $objectives['retention'],
            'owner_id' => $users['morgan@primez.test']->id,
            'deadline' => now()->addDays(9)->toDateString(),
            'original_idea' => 'Three stories across the week: stamp, reward, thank-you.',
        ]);

        $jacket = $this->content($users['jordan@primez.test'], [
            'brand_id' => $harbor->id,
            'campaign_id' => $trail->id,
            'status_id' => $statuses['revision_required'],
            'title' => 'Trail jacket in the rain',
            'format' => 'video',
            'priority' => 'urgent',
            'distribution' => 'both',
            'funnel_stage' => 'consideration',
            'pillar_id' => $pillars['product-showcase'],
            'objective_id' => $objectives['sales'],
            'audience_segment_id' => $hikers->id,
            'owner_id' => $users['casey@primez.test']->id,
            'video_editor_id' => $users['casey@primez.test']->id,
            'reviewer_id' => $users['quinn@primez.test']->id,
            'deadline' => now()->addDays(3)->toDateString(),
            'original_idea' => 'A 25-second clip of the jacket beading water on a real trail, not a studio.',
            'caption_draft' => 'Rain is the test. The trail is the brief.',
            'storyline' => 'Open on rain. Cut to zipper. End on the wearer still walking.',
            'hypothesis' => 'Proof in weather will outperform a flat product photo for this launch.',
            'success_metric' => 'Click-through rate above 1.2% on paid impressions.',
            'complexity' => 4,
        ], [$launch->id]);

        $jacket->approvals()->create([
            'reviewer_id' => $users['quinn@primez.test']->id,
            'decision' => 'revision_requested',
            'notes' => 'The opening is too dark to see the fabric. Add two seconds of the water beading before the logo shot.',
            'submission_number' => 1,
        ]);
        $jacket->activities()->create([
            'user_id' => $users['quinn@primez.test']->id,
            'action' => 'status_changed',
            'description' => 'Revisions requested with a note about the opening shot.',
        ]);

        $packing = $this->content($users['sam@primez.test'], [
            'brand_id' => $harbor->id,
            'campaign_id' => $trail->id,
            'status_id' => $statuses['approved'],
            'title' => 'Packing list for a day hike',
            'format' => 'infographic',
            'priority' => 'medium',
            'funnel_stage' => 'awareness',
            'pillar_id' => $pillars['education'],
            'objective_id' => $objectives['engagement'],
            'owner_id' => $users['riley@primez.test']->id,
            'reviewer_id' => $users['quinn@primez.test']->id,
            'deadline' => now()->addDays(1)->toDateString(),
            'original_idea' => 'Eight items, one of them the shell jacket, without making it a hard sell.',
            'caption_draft' => 'Pack less. Stay out longer.',
            'complexity' => 2,
        ]);
        $packing->approvals()->create([
            'reviewer_id' => $users['quinn@primez.test']->id,
            'decision' => 'approved',
            'notes' => 'Approved for scheduling. Keep the jacket as item six, not the headline.',
            'submission_number' => 1,
        ]);

        $this->content($users['morgan@primez.test'], [
            'brand_id' => $harbor->id,
            'campaign_id' => $trail->id,
            'status_id' => $statuses['ready_for_production'],
            'title' => 'Founder note on double seams',
            'format' => 'text',
            'priority' => 'low',
            'funnel_stage' => 'awareness',
            'pillar_id' => $pillars['brand-awareness'],
            'objective_id' => $objectives['reach'],
            'owner_id' => $users['morgan@primez.test']->id,
            'deadline' => now()->addDays(8)->toDateString(),
            'original_idea' => 'A short founder post about why the seam is doubled, written in plain language.',
            'complexity' => 1,
        ]);

        $this->content($users['jordan@primez.test'], [
            'brand_id' => $harbor->id,
            'campaign_id' => $trail->id,
            'status_id' => $statuses['ready_for_brief'],
            'title' => 'Trail season offer creative',
            'format' => 'ad',
            'priority' => 'high',
            'distribution' => 'paid',
            'funnel_stage' => 'conversion',
            'pillar_id' => $pillars['promotional'],
            'objective_id' => $objectives['sales'],
            'owner_id' => $users['riley@primez.test']->id,
            'deadline' => now()->addDays(6)->toDateString(),
            'hypothesis' => 'A specific offer with a weather proof point will beat a generic percent-off ad.',
            'success_metric' => 'Cost per purchase under the launch target once spend is recorded.',
            'complexity' => 3,
        ]);

        $this->content($users['sam@primez.test'], [
            'brand_id' => $harbor->id,
            'status_id' => $statuses['inbox'],
            'title' => 'Gear repair livestream',
            'format' => 'livestream',
            'priority' => 'low',
            'funnel_stage' => 'retention',
            'pillar_id' => $pillars['community'],
            'objective_id' => $objectives['community-growth'],
            'original_idea' => 'A 30-minute repair hour with questions from comments.',
            'inspiration_url' => 'https://example.com/inspiration/repair-streams',
        ]);

        $pockets = $this->content($users['riley@primez.test'], [
            'brand_id' => $harbor->id,
            'campaign_id' => $trail->id,
            'status_id' => $statuses['published'],
            'title' => 'Five pockets, and what they are for',
            'format' => 'carousel',
            'priority' => 'medium',
            'funnel_stage' => 'consideration',
            'pillar_id' => $pillars['product-showcase'],
            'objective_id' => $objectives['engagement'],
            'owner_id' => $users['riley@primez.test']->id,
            'reviewer_id' => $users['quinn@primez.test']->id,
            'deadline' => now()->subDays(10)->toDateString(),
            'published_at' => now()->subDays(2),
            'created_at' => now()->subDays(4),
            'caption_draft' => 'Each pocket has a job. Swipe for the map pocket.',
            'complexity' => 2,
        ], [$launch->id]);

        $this->version($pockets, $platforms['instagram'], 'carousel', now()->subDays(2), 'published', 'https://example.com/harbor/pockets', [
            'impressions' => 8600, 'reach' => 6400, 'likes' => 410, 'comments_count' => 22, 'shares' => 15, 'saves' => 190, 'link_clicks' => 240, 'leads' => 18, 'ad_spend' => 120, 'revenue' => 540,
        ]);

        $this->content($users['casey@primez.test'], [
            'brand_id' => $northwind->id,
            'campaign_id' => $summer->id,
            'status_id' => $statuses['archived'],
            'title' => 'Last year’s pumpkin draft',
            'format' => 'photo',
            'priority' => 'low',
            'archived_at' => now()->subMonth(),
            'original_idea' => 'Kept for reference. Not part of the current plan.',
        ]);
    }

    private function campaign(Brand $brand, User $owner, string $name, string $status, $start, $end): Campaign
    {
        return Campaign::query()->updateOrCreate(
            ['brand_id' => $brand->id, 'slug' => Str::slug($name)],
            [
                'owner_id' => $owner->id,
                'name' => $name,
                'description' => $name.' for '.$brand->name.'.',
                'objective' => 'Give the team one place to see the work this campaign needs.',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => $status,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>  $tagIds
     */
    private function content(User $creator, array $attributes, array $tagIds = []): ContentItem
    {
        $createdAt = $attributes['created_at'] ?? now();
        unset($attributes['created_at']);

        $existing = ContentItem::query()->where('brand_id', $attributes['brand_id'])->where('title', $attributes['title'])->first();

        if ($existing) {
            return $existing;
        }

        $item = new ContentItem;
        $item->fill(collect($attributes)->only($item->getFillable())->all());
        $item->created_by = $creator->id;
        $item->status_id = $attributes['status_id'];

        foreach (['owner_id', 'copywriter_id', 'video_editor_id', 'reviewer_id', 'archived_at'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $item->{$field} = $attributes[$field];
            }
        }

        $item->save();
        $item->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $attributes['updated_at'] ?? $createdAt,
        ])->saveQuietly();

        if ($tagIds !== []) {
            $item->tags()->sync($tagIds);
        }

        $item->activities()->create([
            'user_id' => $creator->id,
            'action' => 'created',
            'description' => 'Demo idea captured for local development.',
        ]);

        return $item;
    }

    /**
     * @param  array<string, int|float>  $metrics
     */
    private function version(ContentItem $item, int $platformId, string $format, $when, string $publishingStatus, ?string $url = null, array $metrics = []): void
    {
        $version = $item->platformVersions()->updateOrCreate(
            ['platform_id' => $platformId, 'format' => $format],
            [
                'caption' => $item->caption_draft,
                'call_to_action' => $item->call_to_action,
                'scheduled_at' => $when,
                'publishing_status' => $publishingStatus,
                'published_url' => $url,
                'published_at' => $publishingStatus === 'published' ? $when : null,
            ],
        );

        if ($metrics !== []) {
            $version->performanceReports()->updateOrCreate(
                ['period_start' => now()->subDays(7)->toDateString(), 'source' => 'demo_seed'],
                [
                    'period_end' => now()->toDateString(),
                    'notes' => 'Demo seed data for local exploration. This was not imported from a social platform.',
                    ...$metrics,
                ],
            );
        }
    }
}
