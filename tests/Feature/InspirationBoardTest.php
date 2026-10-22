<?php

namespace Tests\Feature;

use App\Models\InspirationItem;
use App\Models\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class InspirationBoardTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
    }

    public function test_a_user_can_save_an_instagram_link_and_open_it_from_the_board(): void
    {
        $brand = $this->makeBrand('Northwind');
        $user = $this->makeUser('social_media_manager', $brand);
        $instagram = Platform::query()->where('slug', 'instagram')->firstOrFail();

        $this->actingAs($user)->post('/inspiration', [
            'name' => 'Cafe reel',
            'platform_id' => $instagram->id,
            'url' => 'https://www.instagram.com/reel/CxampleReel1/',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('inspiration_items', [
            'brand_id' => $brand->id,
            'platform_id' => $instagram->id,
            'user_id' => $user->id,
            'name' => 'Cafe reel',
            'url' => 'https://www.instagram.com/reel/CxampleReel1/',
        ]);

        $this->actingAs($user)->get('/inspiration')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Inspiration/Index')
            ->where('items.data.0.name', 'Cafe reel')
            ->where('items.data.0.embed_url', 'https://www.instagram.com/reel/CxampleReel1/embed')
            ->where('items.data.0.kind', 'iframe')
            ->where('canSave', true)
            ->where('items.data.0.can_edit', true)
            ->where('items.data.0.can_delete', true)
        );

        $item = InspirationItem::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->put('/inspiration/'.$item->id, [
            'name' => 'Renamed reel',
            'platform_id' => $instagram->id,
            'url' => 'https://www.instagram.com/reel/CxampleReel1/',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('inspiration_items', [
            'id' => $item->id,
            'name' => 'Renamed reel',
        ]);

        $this->actingAs($user)->post('/inspiration/'.$item->id.'/archive')->assertRedirect()->assertSessionHas('success');
        $this->assertNotNull($item->fresh()->archived_at);

        $this->actingAs($user)->get('/inspiration')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('archived', false)
            ->where('items.data', [])
        );

        $this->actingAs($user)->get('/inspiration?archived=1')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('archived', true)
            ->where('items.data.0.id', $item->id)
        );

        $this->actingAs($user)->post('/inspiration/'.$item->id.'/archive')->assertRedirect();
        $this->assertNull($item->fresh()->archived_at);
    }

    public function test_inspiration_from_another_brand_is_hidden(): void
    {
        $brand = $this->makeBrand('Northwind');
        $other = $this->makeBrand('Harbor');
        $owner = $this->makeUser('social_media_manager', $brand);
        $outsider = $this->makeUser('social_media_manager', $other);
        $instagram = Platform::query()->where('slug', 'instagram')->firstOrFail();

        InspirationItem::query()->create([
            'brand_id' => $brand->id,
            'platform_id' => $instagram->id,
            'user_id' => $owner->id,
            'url' => 'https://www.instagram.com/p/HiddenPost1/',
        ]);

        $this->actingAs($outsider)->get('/inspiration')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('items.total', 0)
        );
    }
}
