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

        $this->actingAs($user)->get('/posting-schedule')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('entries', fn ($entries) => collect($entries)->where('slot', 'morning')->where('weekday', 'monday')->count() === 2)
            ->where('canAssign', true)
        );

        $entry = PostingScheduleEntry::query()->whereDate('post_on', '2026-11-02')->firstOrFail();

        $this->actingAs($user)->delete(route('posting-schedule.destroy', $entry))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('posting_schedule_entries', ['id' => $entry->id]);
    }
}
