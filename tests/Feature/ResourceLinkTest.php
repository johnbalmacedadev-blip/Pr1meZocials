<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\ResourceLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class ResourceLinkTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
    }

    public function test_a_user_can_save_a_named_google_drive_folder(): void
    {
        $brand = $this->makeBrand('Northwind');
        $user = $this->makeUser('social_media_manager', $brand);
        $url = 'https://drive.google.com/drive/folders/exampleFolderId';

        $this->actingAs($user)->post('/creative-assets', [
            'name' => 'Brand assets',
            'url' => $url,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('resource_links', [
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'name' => 'Brand assets',
            'url' => $url,
        ]);

        $this->actingAs($user)->get('/creative-assets')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Resources/Index')
            ->where('items.data.0.name', 'Brand assets')
            ->where('items.data.0.url', $url)
            ->where('canSave', true)
            ->where('items.data.0.can_edit', true)
            ->where('items.data.0.can_delete', true)
        );

        $item = ResourceLink::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->put('/creative-assets/'.$item->id, [
            'name' => 'Campaign files',
            'url' => $url,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('resource_links', [
            'id' => $item->id,
            'name' => 'Campaign files',
        ]);
    }

    public function test_a_non_drive_link_is_rejected(): void
    {
        $brand = $this->makeBrand('Northwind');
        $user = $this->makeUser('social_media_manager', $brand);

        $this->actingAs($user)->post('/creative-assets', [
            'name' => 'Not a folder',
            'url' => 'https://example.com/files',
        ])->assertSessionHasErrors('url');

        $this->assertDatabaseCount('resource_links', 0);
    }

    public function test_a_folder_from_another_brand_is_hidden(): void
    {
        $brand = $this->makeBrand('Northwind');
        $other = $this->makeBrand('Harbor');
        $owner = $this->makeUser('social_media_manager', $brand);
        $outsider = $this->makeUser('social_media_manager', $other);

        ResourceLink::query()->create([
            'brand_id' => $brand->id,
            'user_id' => $owner->id,
            'name' => 'Hidden folder',
            'url' => 'https://drive.google.com/drive/folders/hiddenFolder',
        ]);

        $this->actingAs($outsider)->get('/creative-assets')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('items.total', 0)
        );
    }

    public function test_search_matches_a_folder_title(): void
    {
        $brand = $this->makeBrand('Northwind');
        $user = $this->makeUser('social_media_manager', $brand);

        ResourceLink::query()->create([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'name' => 'Brand assets',
            'url' => 'https://drive.google.com/drive/folders/brandAssets',
        ]);
        ResourceLink::query()->create([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'name' => 'Campaign files',
            'url' => 'https://drive.google.com/drive/folders/campaignFiles',
        ]);

        $this->actingAs($user)->get('/creative-assets?q=Brand&view=list')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'Brand')
            ->where('filters.view', 'list')
            ->where('items.total', 1)
            ->where('items.data.0.name', 'Brand assets')
        );
    }

    public function test_content_can_link_creative_asset_folders_from_its_brand(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $other = $this->makeBrand('Harbor');
        $user = $this->makeUser('social_media_manager', $brand);
        $folder = ResourceLink::query()->create([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'name' => 'Product Images with User',
            'url' => 'https://drive.google.com/drive/folders/productImages',
        ]);
        $foreign = ResourceLink::query()->create([
            'brand_id' => $other->id,
            'user_id' => $user->id,
            'name' => 'Other brand files',
            'url' => 'https://drive.google.com/drive/folders/otherBrand',
        ]);

        $this->actingAs($user)->post('/content', [
            'title' => 'Folder test',
            'brand_id' => $brand->id,
            'format' => 'video',
        ])->assertRedirect();

        $item = ContentItem::query()->where('title', 'Folder test')->firstOrFail();

        $this->actingAs($user)->put("/content/{$item->id}", [
            'title' => 'Folder test',
            'brand_id' => $brand->id,
            'format' => 'video',
            'resource_link_ids' => [$folder->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('content_resource_link', [
            'content_item_id' => $item->id,
            'resource_link_id' => $folder->id,
        ]);

        $this->actingAs($user)->get("/content/{$item->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('contentItem.resource_links.0.name', 'Product Images with User')
            ->where('options.resource_links.0.id', $folder->id)
        );

        $this->actingAs($user)->put("/content/{$item->id}", [
            'title' => 'Folder test',
            'brand_id' => $brand->id,
            'format' => 'video',
            'resource_link_ids' => [$foreign->id],
        ])->assertSessionHasErrors('resource_link_ids');
    }

    public function test_a_shared_drive_folder_can_be_browsed_and_downloaded_inside_the_app(): void
    {
        $brand = $this->makeBrand('Northwind');
        $other = $this->makeBrand('Harbor');
        $user = $this->makeUser('social_media_manager', $brand);
        $outsider = $this->makeUser('creative', $other);
        $folder = ResourceLink::query()->create([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'name' => 'Product Images with User',
            'url' => 'https://drive.google.com/drive/folders/rootFolder01?usp=sharing',
        ]);

        Http::preventStrayRequests();
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'id=childFolder1')) {
                return Http::response($this->driveHtml([
                    ['id' => 'nestedFile01', 'name' => 'inside.png', 'mime' => 'image/png'],
                ]));
            }

            if (str_contains($url, 'embeddedfolderview')) {
                return Http::response($this->driveHtml([
                    ['id' => 'childFolder1', 'name' => 'Assets', 'folder' => true],
                    ['id' => 'fileImage001', 'name' => 'photo.jpeg', 'mime' => 'image/jpeg', 'thumb' => true],
                ]));
            }

            if (str_contains($url, 'lh3.googleusercontent.com')) {
                return Http::response('thumb-bytes', 200, ['Content-Type' => 'image/jpeg']);
            }

            if (str_contains($url, 'fileImage001')) {
                return Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response('missing', 404);
        });

        $this->actingAs($outsider)->get("/creative-assets/{$folder->id}/files")->assertForbidden();

        $this->actingAs($user)->get("/creative-assets/{$folder->id}/files")->assertOk()
            ->assertJsonPath('entries.0.kind', 'folder')
            ->assertJsonPath('entries.0.name', 'Assets')
            ->assertJsonPath('entries.1.name', 'photo.jpeg')
            ->assertJsonPath('entries.1.previewable', true);

        $this->actingAs($user)->get("/creative-assets/{$folder->id}/files?folder=notInRoot1")->assertNotFound();

        $this->actingAs($user)->get("/creative-assets/{$folder->id}/files?folder=childFolder1")->assertOk()
            ->assertJsonPath('entries.0.name', 'inside.png');

        $preview = $this->actingAs($user)->get("/creative-assets/{$folder->id}/files/fileImage001/preview?folder=rootFolder01");
        $preview->assertOk();
        $this->assertSame('thumb-bytes', $preview->streamedContent());
        $preview->assertHeader('Content-Disposition', 'inline; filename="photo.jpeg"');

        $download = $this->actingAs($user)->get("/creative-assets/{$folder->id}/files/fileImage001/download?folder=rootFolder01");
        $download->assertOk();
        $this->assertSame('image-bytes', $download->streamedContent());
        $download->assertHeader('Content-Disposition', 'attachment; filename="photo.jpeg"');

        $this->actingAs($user)->get("/creative-assets/{$folder->id}/files/otherFile001/download?folder=rootFolder01")->assertNotFound();
    }

    /**
     * @param  list<array{id: string, name: string, folder?: bool, mime?: string, thumb?: bool}>  $entries
     */
    private function driveHtml(array $entries): string
    {
        $rows = '';
        foreach ($entries as $entry) {
            $folder = $entry['folder'] ?? false;
            $href = $folder
                ? "https://drive.google.com/drive/folders/{$entry['id']}"
                : "https://drive.google.com/file/d/{$entry['id']}/view";
            $icon = $folder ? '' : '<div class="flip-entry-list-icon"><img src="https://drive-thirdparty.googleusercontent.com/16/type/'.($entry['mime'] ?? 'application/octet-stream').'" alt=""/></div>';
            $thumb = ($entry['thumb'] ?? false)
                ? '<div class="flip-entry-thumb"><img src="https://lh3.googleusercontent.com/drive-storage/sample=s190" alt="JPEG Image"/></div>'
                : '';
            $label = $folder ? '<div aria-label="Folder"></div>' : '';
            $rows .= '<div class="flip-entry" id="entry-'.$entry['id'].'"><a href="'.$href.'">'.$thumb.$icon.'<div class="flip-entry-title">'.$entry['name'].'</div>'.$label.'</a><div class="flip-entry-last-modified"><div>Oct 17</div></div></div>';
        }

        return '<html><head><title>Images</title></head><body><div id="flip-contents"><div class="flip-entries">'.$rows.'</div></div></body></html>';
    }
}
