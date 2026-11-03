<?php

namespace Tests\Feature;

use App\Models\StudioGeneration;
use App\Models\StudioReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class StudioTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
        Storage::fake('local');
    }

    private function payload(array $overrides = []): array
    {
        return [
            'file' => UploadedFile::fake()->image('flow-result.png', 800, 1000),
            'prompt' => 'A bottle on a wooden table',
            'aspect_ratio' => '4:5',
            'references' => [],
            ...$overrides,
        ];
    }

    public function test_the_page_links_to_flow_and_shows_products_and_who_can_do_what(): void
    {
        $brand = $this->makeBrand('Prime Z');

        $this->actingAs($this->makeUser('marketing_manager', $brand))->get('/studio')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Studio/Index')
            ->where('flowUrl', config('services.google_flow.url'))
            ->has('products', 6)
            ->where('canCreate', true)
            ->where('canDelete', true)
        );

        $this->actingAs($this->makeUser('creative', $brand))->get('/studio')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canCreate', true)
            ->where('canDelete', false)
        );

        $this->actingAs($this->makeUser('reviewer', $brand))->get('/studio')->assertForbidden();
    }

    public function test_every_built_in_product_has_an_image(): void
    {
        $user = $this->makeUser('creative', $this->makeBrand('Prime Z'));

        foreach (['cleanse', 'coffee', 'ginger', 'lumine', 'scrub', 'soap'] as $product) {
            $this->actingAs($user)->get('/studio/products/'.$product.'/image')->assertOk();
        }
        $this->actingAs($user)->get('/studio/products/nothing/image')->assertNotFound();
    }

    public function test_an_image_from_flow_is_saved_with_its_prompt_and_references(): void
    {
        Http::fake();
        $user = $this->makeUser('creative', $this->makeBrand('Prime Z'));

        $this->actingAs($user)->post('/studio/references', ['file' => UploadedFile::fake()->image('pose.jpg'), 'kind' => 'reference'])->assertSessionHasNoErrors();
        $upload = StudioReference::query()->sole();

        $this->actingAs($user)->post('/studio/images', $this->payload(['references' => ['product:cleanse', $upload->key()]]))->assertSessionHasNoErrors();

        $generation = StudioGeneration::query()->sole();
        $this->assertSame('A bottle on a wooden table', $generation->prompt);
        $this->assertSame(['product:cleanse', $upload->key()], $generation->reference_keys);
        $this->assertSame('image/png', $generation->mime_type);
        $this->assertSame(800, $generation->width);
        $this->assertSame(1000, $generation->height);
        Storage::disk('local')->assertExists($generation->path);

        $this->actingAs($user)->get('/studio/images/'.$generation->id)->assertOk();
        $this->actingAs($user)->get('/studio/images/'.$generation->id.'/download')->assertOk();
        $this->actingAs($user)->get('/studio?image='.$generation->id)->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $generation->id)
            ->where('selected.parent_url', null)
        );

        // Nothing in Studio calls Google, so nothing can add to a bill.
        Http::assertNothingSent();
    }

    public function test_a_changed_image_remembers_the_image_it_came_from(): void
    {
        $user = $this->makeUser('creative', $this->makeBrand('Prime Z'));

        $this->actingAs($user)->post('/studio/images', $this->payload(['references' => ['product:soap']]));
        $first = StudioGeneration::query()->sole();

        $this->actingAs($user)->post('/studio/images', $this->payload(['prompt' => 'Make the background lighter', 'parent_id' => $first->id, 'references' => ['product:soap']]))->assertSessionHasNoErrors();

        $second = StudioGeneration::query()->latest('id')->first();
        $this->assertSame($first->id, $second->parent_id);
        $this->assertSame('Make the background lighter', $second->prompt);
        $this->actingAs($user)->get('/studio?image='.$second->id)->assertInertia(fn (Assert $page) => $page
            ->where('selected.parent_url', route('studio.images.show', $first))
        );
    }

    public function test_bad_uploads_save_nothing(): void
    {
        $user = $this->makeUser('creative', $this->makeBrand('Prime Z'));

        $this->actingAs($user)->post('/studio/images', $this->payload(['file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]))->assertSessionHasErrors('file');
        $this->actingAs($user)->post('/studio/images', $this->payload(['file' => null]))->assertSessionHasErrors('file');
        $this->actingAs($user)->post('/studio/images', $this->payload(['prompt' => '']))->assertSessionHasErrors('prompt');
        $this->actingAs($user)->post('/studio/images', $this->payload(['aspect_ratio' => '99:1']))->assertSessionHasErrors('aspect_ratio');
        $this->actingAs($user)->post('/studio/images', $this->payload(['references' => ['product:nothing']]))->assertSessionHasErrors('references');
        $this->actingAs($user)->post('/studio/images', $this->payload(['references' => ['a', 'b', 'c', 'd', 'e', 'f', 'g']]))->assertSessionHasErrors('references');

        $this->assertSame(0, StudioGeneration::query()->count());
    }

    public function test_reviewers_cannot_save_images(): void
    {
        $reviewer = $this->makeUser('reviewer', $this->makeBrand('Prime Z'));

        $this->actingAs($reviewer)->post('/studio/images', $this->payload())->assertForbidden();
    }

    public function test_uploads_and_images_stay_within_the_brand(): void
    {
        $user = $this->makeUser('creative', $this->makeBrand('Prime Z'));
        $outsider = $this->makeUser('creative', $this->makeBrand('Other Brand'));

        $this->actingAs($user)->post('/studio/references', ['file' => UploadedFile::fake()->create('script.pdf', 10, 'application/pdf'), 'kind' => 'reference'])->assertSessionHasErrors('file');
        $this->actingAs($user)->post('/studio/references', ['file' => UploadedFile::fake()->image('bottle.png'), 'kind' => 'product'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/studio/images', $this->payload())->assertSessionHasNoErrors();

        $reference = StudioReference::query()->sole();
        $generation = StudioGeneration::query()->sole();
        $this->assertSame('product', $reference->kind);
        $this->assertSame('bottle', $reference->name);

        $this->actingAs($outsider)->get('/studio/references/'.$reference->id.'/image')->assertForbidden();
        $this->actingAs($outsider)->delete('/studio/references/'.$reference->id)->assertForbidden();
        $this->actingAs($outsider)->get('/studio/images/'.$generation->id)->assertForbidden();
        $this->actingAs($outsider)->post('/studio/images', $this->payload(['references' => [$reference->key()]]))->assertSessionHasErrors('references');
        $this->actingAs($outsider)->post('/studio/images', $this->payload(['parent_id' => $generation->id]))->assertNotFound();

        $this->actingAs($user)->delete('/studio/references/'.$reference->id)->assertRedirect();
        Storage::disk('local')->assertMissing($reference->path);
    }

    public function test_deleting_an_image_removes_its_file(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $owner = $this->makeUser('creative', $brand);
        $colleague = $this->makeUser('creative', $brand);

        $this->actingAs($owner)->post('/studio/images', $this->payload());
        $generation = StudioGeneration::query()->sole();

        $this->actingAs($colleague)->delete('/studio/images/'.$generation->id)->assertForbidden();
        $this->actingAs($owner)->delete('/studio/images/'.$generation->id)->assertRedirect(route('studio.index'));

        $this->assertSame(0, StudioGeneration::query()->count());
        Storage::disk('local')->assertMissing($generation->path);
    }

    public function test_the_update_removes_the_api_columns_and_any_saved_key(): void
    {
        $this->assertFalse(Schema::hasColumn('studio_generations', 'model'));
        $this->assertFalse(Schema::hasColumn('studio_generations', 'image_size'));

        $migration = require database_path('migrations/2026_11_03_140000_move_studio_to_google_flow.php');
        $migration->down();
        Storage::disk('local')->put('gemini-credentials.json', '{}');

        $migration->up();

        Storage::disk('local')->assertMissing('gemini-credentials.json');
        $this->assertFalse(Schema::hasColumn('studio_generations', 'model'));
    }
}
