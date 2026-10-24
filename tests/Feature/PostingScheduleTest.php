<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\PostingScheduleEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class PostingScheduleTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
    }

    public function test_the_facebook_posting_schedule_is_available_to_the_team(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $user = $this->makeUser('social_media_manager', $brand);

        $this->actingAs($user)->get('/posting-schedule')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Schedule/Posting')
            ->has('facebookPosts')
            ->has('week.days', 7)
            ->has('week.start')
            ->has('week.prev')
            ->has('week.next')
            ->where('canPost', true)
        );
    }

    public function test_posting_schedule_can_browse_previous_and_next_weeks(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $user = $this->makeUser('social_media_manager', $brand);

        $this->actingAs($user)->get('/posting-schedule?week=2026-10-20')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('week.start', '2026-10-19')
            ->where('week.end', '2026-10-25')
            ->where('week.prev', '2026-10-12')
            ->where('week.next', '2026-10-26')
            ->where('week.days.0.date', '2026-10-19')
            ->where('week.days.0.name', 'Monday')
            ->where('week.days.6.date', '2026-10-25')
        );
    }

    public function test_posting_compose_accepts_schedule_slot_defaults(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $user = $this->makeUser('social_media_manager', $brand);

        $this->actingAs($user)->get('/posting?'.http_build_query([
            'date' => now()->addDay()->toDateString(),
            'time' => '08:00',
            'slot' => 'morning',
            'weekday' => 'monday',
            'format' => 'reel',
            'title' => 'Motivation Reel',
            'return' => 'schedule',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Posting/Index')
            ->where('composeDefaults.publish_mode', 'schedule')
            ->where('composeDefaults.media_type', 'video')
            ->where('composeDefaults.caption', 'Motivation Reel')
            ->where('composeDefaults.return_to', '/posting-schedule')
            ->where('composeDefaults.slot', 'morning')
        );
    }

    public function test_a_guest_cannot_open_the_posting_schedule(): void
    {
        $this->get('/posting-schedule')->assertRedirect('/login');
    }

    public function test_a_slot_can_hold_content_for_several_mondays(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $user = $this->makeUser('social_media_manager', $brand);

        $this->actingAs($user)->post('/content', [
            'title' => 'Motivation reel for members',
            'brand_id' => $brand->id,
            'format' => 'reel',
        ])->assertRedirect();

        $item = ContentItem::query()->where('title', 'Motivation reel for members')->firstOrFail();

        $this->actingAs($user)->post('/posting-schedule', [
            'weekday' => 'monday',
            'slot' => 'morning',
            'post_on' => '2026-10-26',
            'content_item_id' => $item->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($user)->post('/posting-schedule', [
            'weekday' => 'monday',
            'slot' => 'morning',
            'post_on' => '2026-11-02',
            'content_item_id' => $item->id,
        ])->assertRedirect();

        $this->actingAs($user)->post('/posting-schedule', [
            'weekday' => 'monday',
            'slot' => 'morning',
            'post_on' => '2026-10-27',
            'content_item_id' => $item->id,
        ])->assertSessionHasErrors('post_on');

        $this->actingAs($user)->get('/posting-schedule?week=2026-10-26')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('entries', fn ($entries) => collect($entries)->where('slot', 'morning')->where('weekday', 'monday')->count() === 1)
            ->where('week.start', '2026-10-26')
            ->where('canAssign', true)
        );

        $this->actingAs($user)->get('/posting-schedule?week=2026-11-02')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('entries', fn ($entries) => collect($entries)->where('slot', 'morning')->where('weekday', 'monday')->count() === 1)
            ->where('week.start', '2026-11-02')
        );

        $entry = PostingScheduleEntry::query()->whereDate('post_on', '2026-11-02')->firstOrFail();

        $this->actingAs($user)->delete(route('posting-schedule.destroy', $entry))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('posting_schedule_entries', ['id' => $entry->id]);
    }
}
