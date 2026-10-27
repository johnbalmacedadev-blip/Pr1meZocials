<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Services\NotificationService;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    /** @var list<array{user_ids: list<int>, payload: array<string, mixed>}> */
    private array $queued = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();

        // Record pushes instead of contacting real push services.
        $this->app->instance(WebPushService::class, new class($this->queued) extends WebPushService
        {
            public function __construct(private array &$log) {}

            public function queue(array $userIds, array $payload): void
            {
                $this->log[] = ['user_ids' => $userIds, 'payload' => $payload];
            }

            public function flush(): int
            {
                return count($this->log);
            }

            public function publicKey(): ?string
            {
                return 'test-public-key';
            }
        });
    }

    public function test_manifest_and_service_worker_are_served_publicly(): void
    {
        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', url('/dashboard').'?source=pwa')
            ->assertJsonCount(3, 'icons');

        $response = $this->get('/sw.js')->assertOk()->assertHeader('Service-Worker-Allowed');
        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("addEventListener('push'", $response->getContent());
    }

    public function test_devices_can_subscribe_and_unsubscribe(): void
    {
        $user = $this->makeUser('creative', $this->makeBrand());
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123';

        $this->actingAs($user)->getJson('/push/key')->assertOk()->assertJsonPath('public_key', 'test-public-key');

        $this->actingAs($user)->postJson('/push/subscriptions', [
            'endpoint' => 'http://insecure.example/push',
            'keys' => ['p256dh' => 'key', 'auth' => 'secret'],
        ])->assertUnprocessable();

        $payload = ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPublicKey', 'auth' => 'AuthSecret']];
        $this->actingAs($user)->postJson('/push/subscriptions', $payload)->assertOk();
        $this->actingAs($user)->postJson('/push/subscriptions', $payload)->assertOk();
        $this->assertSame(1, PushSubscription::query()->where('user_id', $user->id)->count());

        // The same phone signing in as someone else moves the subscription to them.
        $other = $this->makeUser('creative', $this->makeBrand('Other'));
        $this->actingAs($other)->postJson('/push/subscriptions', $payload)->assertOk();
        $this->assertSame($other->id, PushSubscription::query()->sole()->user_id);

        $this->actingAs($other)->postJson('/push/subscriptions/delete', ['endpoint' => $endpoint])->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_in_app_notifications_and_chat_messages_are_pushed(): void
    {
        $brand = $this->makeBrand();
        $alice = $this->makeUser('marketing_manager', $brand);
        $bob = $this->makeUser('creative', $brand);

        app(NotificationService::class)->send($bob, 'assigned', 'Task assigned', 'You were assigned.', 'assigned-push-test', ['content_id' => 7]);
        $this->assertSame([$bob->id], $this->queued[0]['user_ids']);
        $this->assertSame('Task assigned', $this->queued[0]['payload']['title']);
        $this->assertStringEndsWith('/content/7', $this->queued[0]['payload']['url']);

        $this->queued = [];
        $teamId = $this->actingAs($bob)->postJson('/chat/sync')->json('team_id');
        $this->actingAs($alice)->postJson("/chat/conversations/{$teamId}/messages", ['body' => 'Standup in 5'])->assertOk();

        $push = collect($this->queued)->firstWhere('payload.tag', 'chat-'.$teamId);
        $this->assertNotNull($push);
        $this->assertSame([$bob->id], $push['user_ids']);
        $this->assertSame($alice->name.' · Team chat', $push['payload']['title']);
        $this->assertSame('Standup in 5', $push['payload']['body']);
    }

    public function test_test_button_needs_a_subscribed_device(): void
    {
        $user = $this->makeUser('creative', $this->makeBrand());

        $this->actingAs($user)->post('/push/test')->assertRedirect()->assertSessionHas('error');

        PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/xyz',
            'endpoint_hash' => PushSubscription::hashEndpoint('https://fcm.googleapis.com/fcm/send/xyz'),
            'public_key' => 'BPublicKey',
            'auth_token' => 'AuthSecret',
        ]);

        $this->actingAs($user)->post('/push/test')->assertRedirect()->assertSessionHas('success');
    }
}
