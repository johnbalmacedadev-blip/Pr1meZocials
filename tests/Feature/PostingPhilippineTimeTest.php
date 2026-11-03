<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class PostingPhilippineTimeTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    private User $manager;

    private SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();

        $brand = $this->makeBrand();
        $this->manager = $this->makeUser('marketing_manager', $brand);
        $this->account = SocialAccount::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => Platform::query()->where('slug', 'facebook')->firstOrFail()->id,
            'name' => 'Prime Z Page',
            'external_id' => '111222333',
            'access_token' => 'page-token-secret',
            'is_active' => true,
        ]);
        $this->manager->socialAccounts()->sync([$this->account->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function publishedPost(string $utc): SocialPost
    {
        return SocialPost::query()->create([
            'social_account_id' => $this->account->id,
            'user_id' => $this->manager->id,
            'caption' => 'Your season of harvest will come.',
            'media_type' => 'image',
            'facebook_post_id' => '111222333_42',
            'status' => 'published',
            'published_at' => Carbon::parse($utc, 'UTC'),
        ]);
    }

    private function scheduleWeekPost(string $week): ?array
    {
        $found = null;
        $this->actingAs($this->manager)->get('/posting-schedule?week='.$week)->assertOk()
            ->assertInertia(function (Assert $page) use (&$found) {
                $found = $page->toArray()['props']['facebookPosts'][0] ?? null;
            });

        return $found;
    }

    public function test_an_8am_philippine_post_lands_in_that_mornings_slot(): void
    {
        // 8:00 AM on Tuesday Nov 3 in Manila is midnight UTC.
        $this->publishedPost('2026-11-03 00:00:00');

        $post = $this->scheduleWeekPost('2026-11-02');

        $this->assertSame('2026-11-03', $post['date']);
        $this->assertSame('tuesday', $post['weekday']);
        $this->assertSame('morning', $post['slot']);
        $this->assertStringEndsWith('+08:00', $post['at']);
    }

    public function test_a_post_before_8am_philippine_time_stays_on_the_philippine_date_and_week(): void
    {
        // 1:00 AM on Monday Nov 9 in Manila is still Sunday Nov 8 in UTC.
        $this->publishedPost('2026-11-08 17:00:00');

        $this->assertNull($this->scheduleWeekPost('2026-11-02'));

        $post = $this->scheduleWeekPost('2026-11-09');
        $this->assertSame('2026-11-09', $post['date']);
        $this->assertSame('monday', $post['weekday']);
    }

    public function test_a_scheduled_post_goes_to_facebook_at_the_philippine_time_chosen(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-11-03 00:00:00', 'UTC'));
        Http::fake(['graph.facebook.com/*/photos' => Http::response(['id' => 'photo9', 'post_id' => '111222333_777'], 200)]);

        // 9:00 AM on Nov 3 in Manila is only an hour away.
        $this->actingAs($this->manager)->post('/posting', [
            'social_account_id' => $this->account->id,
            'caption' => 'Scheduled post',
            'media_type' => 'image',
            'publish_mode' => 'schedule',
            'scheduled_publish_at' => '2026-11-03T09:00',
            'media' => [UploadedFile::fake()->image('poster.jpg', 400, 400)],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $expected = Carbon::parse('2026-11-03 09:00:00', 'Asia/Manila');
        $this->assertTrue($expected->equalTo(SocialPost::query()->sole()->scheduled_publish_at));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/photos')
            && (int) ($request->data()['scheduled_publish_time'] ?? collect($request->data())->firstWhere('name', 'scheduled_publish_time')['contents'] ?? 0) === $expected->getTimestamp());

        $post = $this->scheduleWeekPost('2026-11-02');
        $this->assertSame('2026-11-03', $post['date']);
        $this->assertSame('morning', $post['slot']);
    }

    public function test_a_published_post_can_be_moved_on_calendars_without_touching_facebook(): void
    {
        Http::fake();
        $post = $this->publishedPost('2026-11-03 00:00:00');
        $realDate = $post->published_at->copy();

        $this->actingAs($this->manager)->put('/posting/'.$post->id.'/calendar-date', ['calendar_at' => '2026-11-11T08:00'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $post->refresh();
        $this->assertTrue($realDate->equalTo($post->published_at));
        $this->assertTrue(Carbon::parse('2026-11-11 08:00:00', 'Asia/Manila')->equalTo($post->calendar_at));
        Http::assertNothingSent();

        $this->assertNull($this->scheduleWeekPost('2026-11-02'));
        $moved = $this->scheduleWeekPost('2026-11-09');
        $this->assertSame('2026-11-11', $moved['date']);
        $this->assertSame('wednesday', $moved['weekday']);
        $this->assertSame('morning', $moved['slot']);
        $this->assertTrue($moved['moved']);

        $this->actingAs($this->manager)->get('/calendar?date=2026-11-11&focus=publish')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('events', fn ($events) => collect($events)
                ->contains(fn ($event) => ($event['id'] ?? null) === 'facebook-'.$post->id && $event['date'] === '2026-11-11' && $event['time'] === '08:00')));

        // Going back uses the real date again.
        $this->actingAs($this->manager)->put('/posting/'.$post->id.'/calendar-date', ['calendar_at' => null])->assertSessionHasNoErrors();
        $this->assertNull($post->fresh()->calendar_at);
        $this->assertSame('2026-11-03', $this->scheduleWeekPost('2026-11-02')['date']);
    }

    public function test_only_published_posts_can_move_and_only_by_people_with_access(): void
    {
        $post = $this->publishedPost('2026-11-03 00:00:00');
        $outsider = $this->makeUser('marketing_manager', $this->makeBrand('Other'));
        $reviewer = $this->makeUser('reviewer', $this->account->brand);

        $this->actingAs($outsider)->put('/posting/'.$post->id.'/calendar-date', ['calendar_at' => '2026-11-11T08:00'])->assertForbidden();
        $this->actingAs($reviewer)->put('/posting/'.$post->id.'/calendar-date', ['calendar_at' => '2026-11-11T08:00'])->assertForbidden();
        $this->actingAs($this->manager)->put('/posting/'.$post->id.'/calendar-date', ['calendar_at' => 'soon'])->assertSessionHasErrors('calendar_at');
        $this->assertNull($post->fresh()->calendar_at);

        $post->update(['status' => 'scheduled', 'published_at' => null, 'scheduled_publish_at' => now()->addDay()]);
        $this->actingAs($this->manager)->put('/posting/'.$post->id.'/calendar-date', ['calendar_at' => '2026-11-11T08:00'])->assertSessionHasErrors('calendar_at');
        $this->assertNull($post->fresh()->calendar_at);
    }
}
