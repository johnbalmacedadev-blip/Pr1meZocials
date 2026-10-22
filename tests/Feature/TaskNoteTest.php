<?php

namespace Tests\Feature;

use App\Models\TaskNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class TaskNoteTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
    }

    public function test_a_note_appears_on_the_recipients_pending_board_until_it_is_done(): void
    {
        $brand = $this->makeBrand('Northwind');
        $sender = $this->makeUser('social_media_manager', $brand, ['name' => 'Jordan Blake']);
        $recipient = $this->makeUser('creative', $brand, ['name' => 'Casey Brooks']);
        $outsider = $this->makeUser('creative', $this->makeBrand('Harbor'));

        $this->actingAs($sender)->post('/task-notes', [
            'recipient_id' => $recipient->id,
            'body' => 'Film the product close-ups today.',
        ])->assertRedirect()->assertSessionHas('success');

        $note = TaskNote::query()->firstOrFail();
        $this->assertNull($note->completed_at);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $recipient->id,
            'dedupe_key' => 'task-note-'.$note->id,
        ]);

        $this->actingAs($recipient)->get('/board')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Board/Index')
            ->where('notes.0.body', 'Film the product close-ups today.')
            ->where('notes.0.sender.name', 'Jordan Blake')
        );

        $this->actingAs($sender)->get('/board')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('notes', [])
        );

        $this->actingAs($sender)->get('/team/'.$recipient->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Team/Show')
            ->where('notes.0.body', 'Film the product close-ups today.')
        );

        $this->actingAs($outsider)->post('/task-notes', [
            'recipient_id' => $recipient->id,
            'body' => 'This should not land.',
        ])->assertForbidden();

        $this->actingAs($recipient)->post('/task-notes/'.$note->id.'/complete')->assertRedirect();
        $this->assertNotNull($note->refresh()->completed_at);

        $this->actingAs($recipient)->get('/board')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('notes', [])
        );
    }
}
