<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class SocialPostingTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
        Storage::fake('local');
        config([
            'facebook.app_id' => 'test-app-id',
            'facebook.app_secret' => 'test-app-secret',
            'facebook.redirect_uri' => 'http://localhost/posting/facebook/callback',
        ]);
    }

    public function test_publisher_can_post_image_and_refresh_metrics(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('social_media_manager', $brand);
        $facebook = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $account = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'Prime Z Page',
            'external_id' => '111222333',
            'access_token' => 'page-token-secret',
            'handle' => '@primez',
            'is_active' => true,
        ]);
        $manager->socialAccounts()->sync([$account->id]);

        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['id' => 'photo1', 'post_id' => '111222333_999'], 200)
                ->push([
                    'permalink_url' => 'https://facebook.com/111222333/posts/999',
                    'shares' => ['count' => 2],
                    'likes' => ['summary' => ['total_count' => 10]],
                    'comments' => ['summary' => ['total_count' => 3]],
                    'insights' => [
                        'data' => [
                            ['name' => 'post_impressions_unique', 'values' => [['value' => 100]]],
                            ['name' => 'post_video_views', 'values' => [['value' => 0]]],
                        ],
                    ],
                ], 200)
                ->push([
                    'permalink_url' => 'https://facebook.com/111222333/posts/999',
                    'shares' => ['count' => 4],
                    'likes' => ['summary' => ['total_count' => 20]],
                    'comments' => ['summary' => ['total_count' => 5]],
                    'insights' => [
                        'data' => [
                            ['name' => 'post_impressions_unique', 'values' => [['value' => 200]]],
                        ],
                    ],
                ], 200),
        ]);

        $this->actingAs($manager)->post('/posting', [
            'social_account_id' => $account->id,
            'caption' => 'Hello from Prime Z',
            'media_type' => 'image',
            'publish_mode' => 'now',
            'media' => [UploadedFile::fake()->image('poster.jpg', 800, 800)],
        ])->assertRedirect();

        $post = SocialPost::query()->firstOrFail();
        $this->assertSame('published', $post->status);
        $this->assertSame('111222333_999', $post->facebook_post_id);
        $this->assertSame(10, $post->likes_count);
        $this->assertSame(3, $post->comments_count);
        $this->assertSame(15.0, (float) $post->engagement_rate);

        $this->actingAs($manager)->post('/posting/'.$post->id.'/sync')->assertRedirect();
        $this->assertSame(20, $post->fresh()->likes_count);
        $this->assertSame(5, $post->fresh()->comments_count);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $manager->id,
            'type' => 'facebook_post_published',
            'title' => 'Posted to Facebook',
        ]);
    }

    public function test_publish_stays_successful_when_metrics_sync_fails(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('social_media_manager', $brand);
        $facebook = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $account = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'Prime Z Page',
            'external_id' => '111222333',
            'access_token' => 'page-token-secret',
            'handle' => '@primez',
            'is_active' => true,
        ]);
        $manager->socialAccounts()->sync([$account->id]);

        Http::fake([
            'graph.facebook.com/*/photos' => Http::response(['id' => 'photo1', 'post_id' => '111222333_888'], 200),
            'graph.facebook.com/*' => Http::response(['error' => ['message' => '(#100) Insights unavailable']], 400),
        ]);

        $this->actingAs($manager)->post('/posting', [
            'social_account_id' => $account->id,
            'caption' => 'Posted even without insights',
            'media_type' => 'image',
            'publish_mode' => 'now',
            'media' => [UploadedFile::fake()->image('poster.jpg', 400, 400)],
        ])->assertRedirect();

        $post = SocialPost::query()->firstOrFail();
        $this->assertSame('published', $post->status);
        $this->assertSame('111222333_888', $post->facebook_post_id);
        $this->assertNull($post->error_message);
        $this->assertSame(1, UserNotification::query()->where('user_id', $manager->id)->count());
    }

    public function test_meta_oauth_callback_syncs_all_managed_pages(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('social_media_manager', $brand);

        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::sequence()
                ->push(['access_token' => 'short-user-token', 'expires_in' => 3600], 200)
                ->push(['access_token' => 'long-user-token', 'expires_in' => 5184000], 200),
            'graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => '111',
                        'name' => 'Pr1me Z',
                        'access_token' => 'page-token-a',
                        'username' => 'primez',
                        'link' => 'https://www.facebook.com/primez',
                    ],
                    [
                        'id' => '222',
                        'name' => 'One UP',
                        'access_token' => 'page-token-b',
                        'username' => null,
                        'link' => 'https://www.facebook.com/222',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($manager)
            ->withSession(['facebook_oauth_state' => 'state-123'])
            ->get('/posting/facebook/callback?code=meta-code&state=state-123')
            ->assertRedirect(route('posting.index'));

        $manager->refresh();
        $this->assertSame('long-user-token', $manager->facebook_user_token);
        $this->assertTrue($manager->hasFacebookConnection());

        $this->assertDatabaseHas('social_accounts', [
            'external_id' => '111',
            'name' => 'Pr1me Z',
            'brand_id' => $brand->id,
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('social_accounts', [
            'external_id' => '222',
            'name' => 'One UP',
            'brand_id' => $brand->id,
            'is_active' => 1,
        ]);

        $this->actingAs($manager)->get('/posting')->assertInertia(fn ($page) => $page
            ->component('Posting/Index')
            ->where('metaConnected', true)
            ->has('pages', 2));
    }

    public function test_publisher_can_toggle_page_active_for_posting_dropdown(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('social_media_manager', $brand);
        $facebook = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $active = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'One UP',
            'external_id' => '111',
            'access_token' => 'token-a',
            'is_active' => true,
        ]);
        $inactive = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'Ginger',
            'external_id' => '222',
            'access_token' => 'token-b',
            'is_active' => false,
        ]);

        $this->actingAs($manager)->get('/posting')->assertInertia(fn ($page) => $page
            ->component('Posting/Index')
            ->has('pages', 2)
            ->where('pages.0.is_active', true)
            ->where('pages.1.is_active', false));

        $this->actingAs($manager)
            ->patch('/posting/pages/'.$inactive->id, ['is_active' => true])
            ->assertRedirect();

        $this->assertTrue($inactive->fresh()->is_active);

        $this->actingAs($manager)
            ->patch('/posting/pages/'.$active->id, ['is_active' => false])
            ->assertRedirect();

        $this->assertFalse($active->fresh()->is_active);
    }

    public function test_meta_refresh_preserves_inactive_page_choice(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('social_media_manager', $brand);
        $facebook = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $manager->forceFill([
            'facebook_user_token' => 'user-token',
            'facebook_token_expires_at' => now()->addDays(30),
        ])->save();

        SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'One UP',
            'external_id' => '222',
            'access_token' => 'old-token',
            'is_active' => false,
        ]);

        Http::fake([
            'graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => '222',
                        'name' => 'One UP',
                        'access_token' => 'new-token',
                        'username' => null,
                        'link' => 'https://www.facebook.com/222',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($manager)->post('/posting/facebook/refresh', [
            'brand_id' => $brand->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('social_accounts', [
            'external_id' => '222',
            'is_active' => 0,
        ]);
    }

    public function test_publisher_can_delete_failed_post_from_history(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('social_media_manager', $brand);
        $facebook = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $account = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'Prime Z Page',
            'external_id' => '111222333',
            'access_token' => 'page-token',
            'is_active' => true,
        ]);

        $manager->socialAccounts()->sync([$account->id]);

        $failed = SocialPost::query()->create([
            'social_account_id' => $account->id,
            'user_id' => $manager->id,
            'caption' => 'failed attempt',
            'media_type' => 'image',
            'media_path' => 'social-posts/1/fail.jpg',
            'media_name' => 'fail.jpg',
            'status' => 'failed',
            'error_message' => 'cURL error 60',
        ]);

        $published = SocialPost::query()->create([
            'social_account_id' => $account->id,
            'user_id' => $manager->id,
            'caption' => 'live post',
            'media_type' => 'image',
            'media_path' => 'social-posts/1/ok.jpg',
            'media_name' => 'ok.jpg',
            'status' => 'published',
            'facebook_post_id' => '111222333_1',
        ]);

        Storage::disk('local')->put('social-posts/1/fail.jpg', 'x');

        $this->actingAs($manager)->delete('/posting/'.$failed->id)->assertRedirect();
        $this->assertDatabaseMissing('social_posts', ['id' => $failed->id]);
        $this->assertFalse(Storage::disk('local')->exists('social-posts/1/fail.jpg'));

        $this->actingAs($manager)->delete('/posting/'.$published->id)->assertStatus(422);
        $this->assertDatabaseHas('social_posts', ['id' => $published->id]);
    }

    public function test_publisher_cannot_post_to_unassigned_page(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('social_media_manager', $brand);
        $facebook = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $allowed = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'Allowed Page',
            'external_id' => '111',
            'access_token' => 'token-a',
            'is_active' => true,
        ]);
        $denied = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'Denied Page',
            'external_id' => '222',
            'access_token' => 'token-b',
            'is_active' => true,
        ]);
        $manager->socialAccounts()->sync([$allowed->id]);

        $this->actingAs($manager)->post('/posting', [
            'social_account_id' => $denied->id,
            'caption' => 'Should fail',
            'media_type' => 'image',
            'publish_mode' => 'now',
            'media' => [UploadedFile::fake()->image('poster.jpg', 200, 200)],
        ])->assertForbidden();
    }

    public function test_publisher_can_post_multiple_images_as_one_facebook_post(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('social_media_manager', $brand);
        $facebook = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $account = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'Prime Z Page',
            'external_id' => '111222333',
            'access_token' => 'page-token-secret',
            'is_active' => true,
        ]);
        $manager->socialAccounts()->sync([$account->id]);

        Http::fake([
            'graph.facebook.com/*/photos' => Http::sequence()
                ->push(['id' => 'photo-a'], 200)
                ->push(['id' => 'photo-b'], 200),
            'graph.facebook.com/*/feed' => Http::response(['id' => '111222333_555'], 200),
            'graph.facebook.com/*' => Http::response([
                'permalink_url' => 'https://facebook.com/111222333/posts/555',
                'shares' => ['count' => 0],
                'likes' => ['summary' => ['total_count' => 0]],
                'comments' => ['summary' => ['total_count' => 0]],
                'insights' => ['data' => []],
            ], 200),
        ]);

        $this->actingAs($manager)->post('/posting', [
            'social_account_id' => $account->id,
            'caption' => 'Multi image post',
            'media_type' => 'image',
            'publish_mode' => 'now',
            'media' => [
                UploadedFile::fake()->image('one.jpg', 400, 400),
                UploadedFile::fake()->image('two.jpg', 400, 400),
            ],
        ])->assertRedirect();

        $post = SocialPost::query()->firstOrFail();
        $this->assertSame('published', $post->status);
        $this->assertSame('111222333_555', $post->facebook_post_id);
        $this->assertCount(2, $post->media_files);
        $this->assertStringContainsString('+ 1 more', (string) $post->media_name);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/photos'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/feed'));
    }

    public function test_publisher_can_schedule_post_on_facebook_for_calendar(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('social_media_manager', $brand);
        $facebook = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $account = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'Prime Z Page',
            'external_id' => '111222333',
            'access_token' => 'page-token-secret',
            'is_active' => true,
        ]);
        $manager->socialAccounts()->sync([$account->id]);

        $scheduleAt = now()->addHours(2);

        Http::fake([
            'graph.facebook.com/*/photos' => Http::response(['id' => 'photo9', 'post_id' => '111222333_777'], 200),
        ]);

        $this->actingAs($manager)->post('/posting', [
            'social_account_id' => $account->id,
            'caption' => 'Scheduled Facebook post',
            'media_type' => 'image',
            'publish_mode' => 'schedule',
            'scheduled_publish_at' => $scheduleAt->format('Y-m-d H:i:s'),
            'media' => [UploadedFile::fake()->image('poster.jpg', 400, 400)],
        ])->assertRedirect();

        $post = SocialPost::query()->firstOrFail();
        $this->assertSame('scheduled', $post->status);
        $this->assertSame('111222333_777', $post->facebook_post_id);
        $this->assertNotNull($post->scheduled_publish_at);
        $this->assertNull($post->published_at);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/photos'));

        $this->actingAs($manager)->get('/calendar?date='.$scheduleAt->toDateString().'&focus=publish')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Calendar/Index')
                ->has('events', 1)
                ->where('events.0.kind', 'facebook')
                ->where('events.0.id', 'facebook-'.$post->id));
    }

    public function test_published_facebook_posts_appear_on_dashboard_and_calendar(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('social_media_manager', $brand);
        $facebook = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $account = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'Prime Z Page',
            'external_id' => '111222333',
            'access_token' => 'page-token-secret',
            'is_active' => true,
        ]);
        $manager->socialAccounts()->sync([$account->id]);

        $publishedAt = now()->setTime(19, 0);

        $post = SocialPost::query()->create([
            'social_account_id' => $account->id,
            'user_id' => $manager->id,
            'caption' => 'Prime Z photo probe',
            'media_type' => 'image',
            'facebook_post_id' => '111222333_42',
            'status' => 'published',
            'published_at' => $publishedAt,
        ]);

        $this->actingAs($manager)->get('/calendar?date='.$publishedAt->toDateString().'&focus=publish')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Calendar/Index')
                ->where('events', fn ($events) => collect($events)->contains(fn ($event) => ($event['id'] ?? null) === 'facebook-'.$post->id)));

        $this->actingAs($manager)->get('/dashboard?month='.$publishedAt->toDateString().'&from='.$publishedAt->toDateString().'&to='.$publishedAt->toDateString())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('schedule.events', fn ($events) => collect($events)->contains(fn ($event) => ($event['id'] ?? null) === 'facebook-'.$post->id))
                ->where('metrics.kpis.scheduled_this_week', fn ($total) => (int) $total >= 1)
                ->where('metrics.kpis.published_in_period', fn ($total) => (int) $total >= 1));
    }

    public function test_settings_can_assign_facebook_page_posting_access(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $admin = $this->makeUser('administrator', $brand);
        $manager = $this->makeUser('social_media_manager', $brand);
        $facebook = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $page = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'One UP',
            'external_id' => '333',
            'access_token' => 'token',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put('/settings/users/'.$manager->id, [
            'name' => $manager->name,
            'email' => $manager->email,
            'password' => '',
            'job_title' => $manager->job_title,
            'is_active' => true,
            'role_ids' => $manager->roles()->pluck('roles.id')->all(),
            'brand_ids' => [$brand->id],
            'social_account_ids' => [$page->id],
        ])->assertRedirect();

        $this->assertTrue($manager->fresh()->canAccessSocialAccount($page->id));
        $this->assertDatabaseHas('social_account_user', [
            'user_id' => $manager->id,
            'social_account_id' => $page->id,
        ]);

        $this->actingAs($admin)->get('/settings?section=users')->assertInertia(fn ($pageAssert) => $pageAssert
            ->component('Settings/Index')
            ->has('facebookPages', 1));
    }

    public function test_publisher_can_update_caption_on_published_post(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('social_media_manager', $brand);
        $facebook = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $account = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'Prime Z Page',
            'external_id' => '111222333',
            'access_token' => 'page-token-secret',
            'is_active' => true,
        ]);
        $manager->socialAccounts()->sync([$account->id]);

        $post = SocialPost::query()->create([
            'social_account_id' => $account->id,
            'user_id' => $manager->id,
            'caption' => 'Old caption',
            'media_type' => 'image',
            'media_path' => 'social-posts/1/ok.jpg',
            'media_name' => 'ok.jpg',
            'facebook_post_id' => '111222333_999',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->actingAs($manager)->put('/posting/'.$post->id, [
            'caption' => 'Updated caption for history edit',
        ])->assertRedirect();

        $this->assertSame('Updated caption for history edit', $post->fresh()->caption);
        Http::assertSent(fn ($request) => str_contains($request->url(), '111222333_999'));
    }

    public function test_history_focus_opens_specific_post(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('social_media_manager', $brand);
        $facebook = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $account = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $facebook->id,
            'name' => 'Prime Z Page',
            'external_id' => '111222333',
            'access_token' => 'page-token-secret',
            'is_active' => true,
        ]);
        $manager->socialAccounts()->sync([$account->id]);

        $post = SocialPost::query()->create([
            'social_account_id' => $account->id,
            'user_id' => $manager->id,
            'caption' => 'Focused post',
            'media_type' => 'image',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($manager)->get('/posting?tab=history&post='.$post->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Posting/Index')
                ->where('focus.tab', 'history')
                ->where('focus.post_id', $post->id)
                ->where('posts.0.id', $post->id));
    }

    public function test_creative_without_publish_permission_cannot_open_posting(): void
    {
        $brand = $this->makeBrand();
        $creative = $this->makeUser('creative', $brand);

        $this->actingAs($creative)->get('/posting')->assertForbidden();
    }
}
