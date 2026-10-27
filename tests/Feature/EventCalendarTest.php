<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class EventCalendarTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
    }

    public function test_manager_can_create_weekly_event_and_update_occurrence_media(): void
    {
        Storage::fake('local');

        $brand = $this->makeBrand();
        $manager = $this->makeUser('marketing_manager', $brand);

        $this->actingAs($manager)->post('/events', [
            'brand_id' => $brand->id,
            'title' => 'Friday Night Live',
            'description' => 'Weekly show',
            'location' => 'Main stage',
            'starts_on' => '2026-10-30',
            'ends_on' => '2026-11-20',
            'start_time' => '20:00',
            'end_time' => '22:00',
            'recurrence' => 'weekly',
            'recurrence_weekdays' => [5],
        ])->assertRedirect();

        $event = Event::query()->where('title', 'Friday Night Live')->firstOrFail();
        $this->assertSame('weekly', $event->recurrence);
        $this->assertGreaterThanOrEqual(3, $event->occurrences()->count());

        $this->actingAs($manager)->get('/events?date=2026-11-01')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Events/Index')
            ->has('occurrences')
            ->has('series', 1)
        );

        $occurrence = $event->occurrences()->orderBy('occurs_on')->firstOrFail();

        $this->actingAs($manager)->post('/event-occurrences/'.$occurrence->id, [
            'title_override' => 'Halloween special',
            'notes' => 'New poster this week',
            'start_time' => '20:00',
            'end_time' => '23:00',
            'status' => 'scheduled',
            'poster' => UploadedFile::fake()->image('invite.jpg'),
            'video' => UploadedFile::fake()->create('invite.mp4', 1200, 'video/mp4'),
        ])->assertRedirect();

        $occurrence->refresh();
        $this->assertSame('Halloween special', $occurrence->title_override);
        $this->assertNotNull($occurrence->poster_path);
        $this->assertNotNull($occurrence->video_path);
        Storage::disk('local')->assertExists($occurrence->poster_path);
        Storage::disk('local')->assertExists($occurrence->video_path);

        $this->actingAs($manager)->get('/event-occurrences/'.$occurrence->id.'/media/poster')->assertOk();
        $this->actingAs($manager)->get('/events/'.$event->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('event.title', 'Friday Night Live')
        );
    }

    public function test_creative_without_delete_can_view_but_not_destroy(): void
    {
        $brand = $this->makeBrand();
        $manager = $this->makeUser('marketing_manager', $brand);
        $creative = $this->makeUser('creative', $brand);

        $event = Event::query()->create([
            'brand_id' => $brand->id,
            'created_by' => $manager->id,
            'title' => 'Saturday Brunch',
            'starts_on' => '2026-11-01',
            'recurrence' => 'none',
            'status' => 'active',
        ]);
        EventOccurrence::query()->create([
            'event_id' => $event->id,
            'occurs_on' => '2026-11-01',
            'status' => 'scheduled',
        ]);

        $this->actingAs($creative)->get('/events')->assertOk();
        $this->actingAs($creative)->delete('/events/'.$event->id)->assertForbidden();
    }
}
