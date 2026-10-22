<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\Platform;
use App\Models\Role;
use App\Models\UserNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class CreativeOperationsTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
    }

    public function test_guest_is_sent_to_login_and_inactive_users_cannot_sign_in(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');

        $brand = $this->makeBrand();
        $user = $this->makeUser('creative', $brand, ['is_active' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $admin = $this->makeUser('administrator', $brand, [
            'username' => 'admin',
            'email' => 'admin@primez.test',
            'password' => 'admin',
        ]);

        $this->post('/login', [
            'email' => 'admin',
            'password' => 'admin',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_administrator_can_create_a_user_and_a_manager_can_create_a_brand_and_campaign(): void
    {
        $brand = $this->makeBrand();
        $admin = $this->makeUser('administrator', $brand);
        $manager = $this->makeUser('marketing_manager', $brand);
        $creativeRole = Role::query()->where('slug', 'creative')->firstOrFail();

        $this->actingAs($admin)->post('/settings/users', [
            'name' => 'New Designer',
            'email' => 'designer@primez.test',
            'password' => 'password',
            'job_title' => 'Designer',
            'is_active' => true,
            'role_ids' => [$creativeRole->id],
            'brand_ids' => [$brand->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'designer@primez.test']);

        $this->actingAs($manager)->post('/settings/brands', [
            'name' => 'Harbor Goods',
            'description' => 'Outdoor retail',
            'website' => 'https://harbor.example',
            'industry' => 'Retail',
            'is_active' => true,
        ])->assertRedirect();

        $this->actingAs($manager)->post('/campaigns', [
            'brand_id' => $brand->id,
            'name' => 'Trail Season',
            'objective' => 'Grow consideration',
            'status' => 'active',
            'member_ids' => [$manager->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('campaigns', ['name' => 'Trail Season', 'brand_id' => $brand->id]);
    }

    public function test_content_moves_through_assignment_revision_approval_and_platform_versions(): void
    {
        $brand = $this->makeBrand();
        $manager = $this->makeUser('marketing_manager', $brand);
        $creative = $this->makeUser('creative', $brand);
        $reviewer = $this->makeUser('reviewer', $brand);
        $instagram = Platform::query()->where('slug', 'instagram')->firstOrFail();
        $tiktok = Platform::query()->where('slug', 'tiktok')->firstOrFail();

        $this->actingAs($manager)->post('/content', [
            'title' => 'Cold brew pour',
            'brand_id' => $brand->id,
            'format' => 'video',
            'original_idea' => 'Show the pour in one continuous shot.',
            'inspiration_url' => 'https://example.com/reference',
        ])->assertRedirect();

        $item = ContentItem::query()->where('title', 'Cold brew pour')->firstOrFail();
        $this->assertNotNull($item->reference_number);
        $this->assertSame('inbox', $item->status->slug);

        $this->actingAs($manager)->put("/content/{$item->id}", [
            'title' => 'Cold brew pour',
            'brand_id' => $brand->id,
            'format' => 'video',
            'deadline' => now()->addDays(5)->toDateString(),
            'original_idea' => 'Show the pour in one continuous shot.',
        ])->assertRedirect();

        $this->actingAs($manager)->put("/content/{$item->id}/assignment", [
            'owner_id' => $creative->id,
            'collaborator_ids' => [$creative->id],
        ])->assertRedirect();

        $item->refresh();
        $deadline = $item->deadline->toDateString();
        $this->assertSame($creative->id, $item->owner_id);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $creative->id,
            'type' => 'assigned',
        ]);

        $this->actingAs($creative)->post("/content/{$item->id}/assets", [
            'file' => $this->pngUpload(),
        ])->assertRedirect();

        $this->assertDatabaseHas('creative_assets', ['content_item_id' => $item->id]);

        foreach (['ready_for_production', 'in_progress', 'internal_review'] as $status) {
            $this->actingAs($creative)->post("/content/{$item->id}/transition", [
                'status' => $status,
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->actingAs($reviewer)->post("/content/{$item->id}/reviews", [
            'decision' => 'revision_requested',
            'notes' => 'Open on the finished cup, not the empty glass.',
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame('in_progress', $item->status->slug);
        $this->assertDatabaseHas('content_approvals', [
            'content_item_id' => $item->id,
            'decision' => 'revision_requested',
        ]);

        $this->actingAs($creative)->post("/content/{$item->id}/transition", ['status' => 'in_progress'])->assertRedirect();
        $this->actingAs($creative)->post("/content/{$item->id}/transition", ['status' => 'internal_review'])->assertRedirect();

        $this->actingAs($reviewer)->post("/content/{$item->id}/reviews", [
            'decision' => 'approved',
            'notes' => 'The opening frame is clear.',
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame('approved', $item->status->slug);
        $this->assertNull($item->published_at);

        $this->actingAs($manager)->post("/content/{$item->id}/versions", [
            'platform_id' => $instagram->id,
            'format' => 'reel',
            'caption' => 'Slow pour, cold glass.',
        ])->assertRedirect();

        $this->actingAs($manager)->post("/content/{$item->id}/versions", [
            'platform_id' => $tiktok->id,
            'format' => 'video',
            'caption' => 'One take. No voiceover.',
            'scheduled_at' => now()->addDays(3)->toDateTimeString(),
        ])->assertRedirect();

        $this->assertSame(2, $item->platformVersions()->count());

        $scheduled = $item->platformVersions()->where('platform_id', $tiktok->id)->firstOrFail();
        $this->actingAs($manager)->post("/versions/{$scheduled->id}/reschedule", [
            'scheduled_at' => now()->addDays(6)->toDateTimeString(),
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame($deadline, $item->deadline->toDateString());

        $this->actingAs($manager)->post("/versions/{$scheduled->id}/publish", [
            'published_url' => 'https://www.tiktok.com/@northwind/video/1',
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame('published', $item->status->slug);
        $this->assertSame($deadline, $item->deadline->toDateString());

        $this->actingAs($manager)->post('/performance', [
            'content_platform_version_id' => $scheduled->id,
            'period_start' => now()->subDays(2)->toDateString(),
            'period_end' => now()->toDateString(),
            'impressions' => 1000,
            'likes' => 40,
            'comments_count' => 10,
            'shares' => 0,
            'saves' => 0,
            'source' => 'platform_api',
        ])->assertRedirect();

        $this->assertDatabaseHas('performance_reports', [
            'content_platform_version_id' => $scheduled->id,
            'source' => 'manual',
            'impressions' => 1000,
        ]);

        $this->actingAs($manager)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('metrics.kpis.published_in_period', 1)
            ->where('metrics.has_content', true)
        );

        $this->actingAs($manager)->get('/board')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Board/Index')
            ->where('cards.0.reference_number', $item->reference_number)
        );

        $this->actingAs($manager)->get('/calendar?focus=publish')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Index')
            ->has('events')
        );

        $this->actingAs($manager)->get('/content?status=published')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Content/Index')
            ->where('content.data.0.id', $item->id)
        );

        $this->actingAs($manager)->get('/search?q=Cold+brew')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('results.0.id', $item->id)
        );
    }

    public function test_users_cannot_read_or_change_another_brands_content(): void
    {
        $owned = $this->makeBrand('Owned');
        $other = $this->makeBrand('Other');
        $creative = $this->makeUser('creative', $owned);
        $manager = $this->makeUser('marketing_manager', $other);

        $this->actingAs($manager)->post('/content', [
            'title' => 'Private launch',
            'brand_id' => $other->id,
            'format' => 'photo',
        ])->assertRedirect();

        $item = ContentItem::query()->where('title', 'Private launch')->firstOrFail();

        $this->actingAs($creative)->get("/content/{$item->id}")->assertForbidden();
        $this->actingAs($creative)->put("/content/{$item->id}", [
            'title' => 'Taken over',
            'brand_id' => $owned->id,
            'format' => 'photo',
        ])->assertForbidden();
    }

    public function test_dashboard_is_empty_until_records_exist_and_notifications_are_not_duplicated(): void
    {
        $brand = $this->makeBrand();
        $manager = $this->makeUser('marketing_manager', $brand);

        $this->actingAs($manager)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('metrics.has_content', false)
            ->where('metrics.kpis.active', 0)
        );

        $service = app(NotificationService::class);
        $service->send($manager, 'assigned', 'Assigned', 'Once', 'assigned:demo', ['content_id' => 1]);
        $service->send($manager, 'assigned', 'Assigned', 'Once', 'assigned:demo', ['content_id' => 1]);

        $this->assertSame(1, UserNotification::query()->where('dedupe_key', 'assigned:demo')->count());
    }

    public function test_archive_and_pagination_keep_records_addressable(): void
    {
        $brand = $this->makeBrand();
        $manager = $this->makeUser('marketing_manager', $brand);

        $first = null;

        for ($index = 1; $index <= 13; $index++) {
            $this->actingAs($manager)->post('/content', [
                'title' => 'Idea '.$index,
                'brand_id' => $brand->id,
                'format' => 'photo',
            ])->assertRedirect();

            $first ??= ContentItem::query()->where('title', 'Idea 1')->first();
        }

        $this->actingAs($manager)->get('/content?page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('content.current_page', 2)
            ->has('content.data', 1)
        );

        $this->actingAs($manager)->post("/content/{$first->id}/archive")->assertRedirect();
        $this->assertSame('archived', $first->fresh()->status->slug);
        $this->assertNotNull($first->fresh()->archived_at);

        $this->actingAs($manager)->get('/content?status=archived')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('content.data.0.id', $first->id)
        );
    }

    public function test_an_assignee_from_another_brand_sees_the_task_on_their_dashboard(): void
    {
        $brand = $this->makeBrand('Northwind');
        $other = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('marketing_manager', $brand);
        $creative = $this->makeUser('creative', $other, ['name' => 'Sidney']);

        $this->actingAs($manager)->post('/content', [
            'title' => 'Customer story',
            'brand_id' => $brand->id,
            'format' => 'photo',
        ])->assertRedirect();

        $item = ContentItem::query()->where('title', 'Customer story')->firstOrFail();

        $this->actingAs($manager)->put("/content/{$item->id}/assignment", [
            'owner_id' => $creative->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertTrue($creative->fresh()->canAccessBrand($brand->id));

        $this->actingAs($creative)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('metrics.tasks.0.id', $item->id)
            ->where('metrics.tasks.0.title', 'Customer story')
            ->where('metrics.tasks.0.role', 'Assigned')
        );

        $this->actingAs($creative)->get('/board')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('cards', fn ($cards) => collect($cards)->contains(fn ($card) => $card['id'] === $item->id))
        );

        $this->actingAs($creative)->get("/content/{$item->id}/edit")->assertForbidden();
        $this->actingAs($creative)->put("/content/{$item->id}", [
            'title' => 'Changed by the assignee',
            'brand_id' => $brand->id,
            'format' => 'photo',
        ])->assertForbidden();

        $this->actingAs($creative)->post("/content/{$item->id}/transition", [
            'status' => 'in_progress',
        ])->assertRedirect();

        $this->actingAs($creative)->post("/content/{$item->id}/comments", [
            'body' => 'Finished cut: https://example.com/video',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('content_comments', [
            'content_item_id' => $item->id,
            'user_id' => $creative->id,
            'body' => 'Finished cut: https://example.com/video',
        ]);

        $this->actingAs($creative)->get("/content/{$item->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('contentItem.abilities.comment', true)
            ->where('contentItem.comments.0.body', 'Finished cut: https://example.com/video')
        );

        $this->actingAs($manager)->put("/content/{$item->id}/assignment", [
            'owner_id' => $creative->id,
            'collaborator_ids' => [$creative->id],
        ])->assertRedirect();

        $this->actingAs($creative)->get("/content/{$item->id}/edit")->assertOk();
    }

    private function pngUpload(): UploadedFile
    {
        $directory = storage_path('framework/testing');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/pixel.png';
        File::put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        return new UploadedFile($path, 'draft.png', 'image/png', null, true);
    }
}
