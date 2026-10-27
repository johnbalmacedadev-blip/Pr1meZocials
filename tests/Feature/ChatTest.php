<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\InspirationItem;
use App\Models\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
    }

    public function test_direct_messages_track_unread_and_seen(): void
    {
        $brand = $this->makeBrand();
        $alice = $this->makeUser('marketing_manager', $brand);
        $bob = $this->makeUser('creative', $brand);

        $conversationId = $this->actingAs($alice)->postJson('/chat/direct/'.$bob->id)->assertOk()->json('conversation_id');
        $this->assertSame($conversationId, $this->actingAs($bob)->postJson('/chat/direct/'.$alice->id)->json('conversation_id'));

        $first = $this->actingAs($alice)->postJson("/chat/conversations/{$conversationId}/messages", ['body' => 'Hi Bob'])->assertOk()->json('message.id');
        $this->actingAs($alice)->postJson("/chat/conversations/{$conversationId}/messages", ['body' => 'Got a minute?'])->assertOk();

        // Bob sees 2 unread before opening the chat (team chat is listed first), and Alice sees him online after his sync.
        $this->actingAs($bob)->postJson('/chat/sync')
            ->assertOk()
            ->assertJsonPath('unread_total', 2)
            ->assertJsonPath('conversations.0.kind', 'team')
            ->assertJsonPath('conversations.1.kind', 'direct')
            ->assertJsonPath('conversations.1.unread', 2)
            ->assertJsonPath('conversations.1.last_message.preview', 'Got a minute?');

        $this->actingAs($alice)->postJson('/chat/sync', ['conversation_id' => $conversationId])
            ->assertJsonPath('users.0.id', $bob->id)
            ->assertJsonPath('users.0.online', true)
            ->assertJsonPath('thread.readers.0.last_read_message_id', null);

        // Opening the thread marks it read: the badge clears and Alice sees "seen".
        $this->actingAs($bob)->postJson('/chat/sync', ['conversation_id' => $conversationId, 'mark_read' => true])
            ->assertJsonCount(2, 'thread.messages');
        $this->actingAs($bob)->postJson('/chat/sync')->assertJsonPath('unread_total', 0);

        $this->actingAs($alice)->postJson('/chat/sync', ['conversation_id' => $conversationId, 'after_id' => $first])
            ->assertJsonCount(1, 'thread.messages')
            ->assertJsonPath('thread.readers.0.last_read_message_id', $first + 1);
    }

    public function test_everyone_is_in_the_team_group_chat(): void
    {
        $brand = $this->makeBrand();
        $alice = $this->makeUser('marketing_manager', $brand);
        $bob = $this->makeUser('creative', $brand);
        $cara = $this->makeUser('creative', $this->makeBrand('Other'));

        $teamId = $this->actingAs($bob)->postJson('/chat/sync')->assertOk()->json('team_id');
        $this->actingAs($cara)->postJson('/chat/sync');

        $this->actingAs($alice)->postJson("/chat/conversations/{$teamId}/messages", ['body' => 'Morning team!'])
            ->assertOk()
            ->assertJsonPath('message.user_name', $alice->name);

        $this->actingAs($bob)->postJson('/chat/sync')->assertJsonPath('unread_total', 1)->assertJsonPath('conversations.0.unread', 1);
        $this->actingAs($cara)->postJson('/chat/sync')->assertJsonPath('conversations.0.name', 'Team chat')->assertJsonPath('unread_total', 1);

        // Bob reads it; Alice sees who has read her message.
        $this->actingAs($bob)->postJson('/chat/sync', ['conversation_id' => $teamId, 'mark_read' => true])->assertJsonCount(1, 'thread.messages');
        $readers = collect($this->actingAs($alice)->postJson('/chat/sync', ['conversation_id' => $teamId])->json('thread.readers'))->keyBy('id');
        $this->assertNotNull($readers[$bob->id]['last_read_message_id']);
        $this->assertNull($readers[$cara->id]['last_read_message_id']);

        // Someone joining later sees the history but starts with nothing unread.
        $dana = $this->makeUser('creative', $brand);
        $this->actingAs($dana)->postJson('/chat/sync', ['conversation_id' => $teamId])
            ->assertJsonPath('unread_total', 0)
            ->assertJsonCount(1, 'thread.messages');
    }

    public function test_outsiders_cannot_read_or_post_in_a_conversation(): void
    {
        $brand = $this->makeBrand();
        $alice = $this->makeUser('marketing_manager', $brand);
        $bob = $this->makeUser('creative', $brand);
        $eve = $this->makeUser('creative', $brand);

        $conversationId = $this->actingAs($alice)->postJson('/chat/direct/'.$bob->id)->json('conversation_id');

        $this->actingAs($eve)->postJson('/chat/sync', ['conversation_id' => $conversationId])->assertForbidden();
        $this->actingAs($eve)->postJson("/chat/conversations/{$conversationId}/messages", ['body' => 'hi'])->assertForbidden();
        $this->actingAs($eve)->getJson("/chat/conversations/{$conversationId}/messages")->assertForbidden();
    }

    public function test_items_from_other_features_can_be_shared_when_visible(): void
    {
        $brand = $this->makeBrand();
        $alice = $this->makeUser('marketing_manager', $brand);
        $bob = $this->makeUser('creative', $brand);
        $outsider = $this->makeUser('marketing_manager', $this->makeBrand('Other'));

        $item = InspirationItem::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => Platform::query()->value('id'),
            'user_id' => $alice->id,
            'name' => 'Great hook idea',
            'url' => 'https://www.tiktok.com/@brand/video/123',
        ]);

        $this->actingAs($alice)->getJson('/chat/attachables?type=inspiration&q=hook')
            ->assertOk()
            ->assertJsonPath('items.0.id', $item->id)
            ->assertJsonPath('items.0.external', true);

        $conversationId = $this->actingAs($alice)->postJson('/chat/direct/'.$bob->id)->json('conversation_id');

        $this->actingAs($alice)->postJson("/chat/conversations/{$conversationId}/messages", [
            'attachment_type' => 'inspiration',
            'attachment_id' => $item->id,
        ])->assertOk()->assertJsonPath('message.attachment.title', 'Great hook idea');

        $otherConversation = $this->actingAs($outsider)->postJson('/chat/direct/'.$bob->id)->json('conversation_id');
        $this->actingAs($outsider)->postJson("/chat/conversations/{$otherConversation}/messages", [
            'attachment_type' => 'inspiration',
            'attachment_id' => $item->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('attachment_id');

        $this->assertSame(1, ChatConversation::query()->findOrFail($conversationId)->messages()->count());
    }

    public function test_messenger_page_renders(): void
    {
        $user = $this->makeUser('creative', $this->makeBrand());

        $this->actingAs($user)->get('/messenger')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Messenger/Index')
            ->where('initialConversationId', null)
        );
    }
}
