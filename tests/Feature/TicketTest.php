<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
        Storage::fake('local');
    }

    public function test_ticket_lands_on_the_board_and_notifies_the_team(): void
    {
        $brand = $this->makeBrand();
        $manager = $this->makeUser('marketing_manager', $brand);
        $lead = $this->makeUser('creative', $brand);
        $helper = $this->makeUser('creative', $brand);

        $this->actingAs($manager)->post('/tickets', [
            'title' => 'Poster for Saturday promo',
            'details' => 'Use the new bundle photo.',
            'brand_id' => $brand->id,
            'format' => 'photo',
            'priority' => 'high',
            'deadline' => now()->addDays(3)->toDateString(),
            'drive_url' => 'https://drive.google.com/drive/folders/abc123',
            'images' => [UploadedFile::fake()->image('ref.jpg', 400, 300)],
            'member_ids' => [$lead->id, $helper->id],
        ])->assertRedirect('/tickets')->assertSessionHasNoErrors();

        $ticket = ContentItem::query()->with('status', 'collaborators', 'assets')->sole();
        $this->assertTrue((bool) $ticket->is_ticket);
        $this->assertSame('ready_for_production', $ticket->status->slug);
        $this->assertSame($lead->id, $ticket->owner_id);
        $this->assertSame([$helper->id], $ticket->collaborators->pluck('id')->all());
        $this->assertSame('Use the new bundle photo.', $ticket->description);
        $this->assertSame('https://drive.google.com/drive/folders/abc123', $ticket->inspiration_url);
        $this->assertCount(1, $ticket->assets);

        $this->assertTrue(UserNotification::query()->where('user_id', $lead->id)->where('type', 'assigned')->exists());
        $this->assertTrue(UserNotification::query()->where('user_id', $helper->id)->where('type', 'assigned')->exists());

        $this->actingAs($manager)->get('/board')->assertInertia(fn (Assert $page) => $page
            ->where('cards.0.id', $ticket->id)
            ->where('cards.0.status.slug', 'ready_for_production'));

        $this->actingAs($manager)->get('/tickets')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->where('tickets.0.id', $ticket->id)
            ->where('tickets.0.images', 1)
            ->where('tickets.0.team.0.id', $lead->id));
    }

    public function test_ticket_needs_a_team_and_a_drive_link(): void
    {
        $brand = $this->makeBrand();
        $manager = $this->makeUser('marketing_manager', $brand);
        $outsider = $this->makeUser('creative', $this->makeBrand('Other'));

        $this->actingAs($manager)->post('/tickets', [
            'title' => 'Anything',
            'brand_id' => $brand->id,
            'format' => 'other',
            'drive_url' => 'https://example.com/file',
            'member_ids' => [$outsider->id],
        ])->assertSessionHasErrors(['member_ids.0']);

        $this->actingAs($manager)->post('/tickets', [
            'title' => 'Anything',
            'brand_id' => $brand->id,
            'format' => 'other',
            'drive_url' => 'https://example.com/file',
            'member_ids' => [$manager->id],
        ])->assertSessionHasErrors(['drive_url']);

        $this->assertSame(0, ContentItem::query()->count());
    }
}
