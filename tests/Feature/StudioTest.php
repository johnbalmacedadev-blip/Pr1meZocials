<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\StudioGeneration;
use App\Models\StudioReference;
use App\Support\GeminiCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class StudioTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    private const KEY = 'AIza-test-key-0123456789-abcd';

    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
        Storage::fake('local');
    }

    private function fakeImage(): void
    {
        Http::fake(['*/v1beta/interactions' => Http::response([
            'id' => 'int_1',
            'steps' => [
                ['type' => 'thought', 'content' => []],
                ['type' => 'model_output', 'content' => [
                    ['type' => 'text', 'text' => 'Here you go.'],
                    ['type' => 'image', 'mime_type' => 'image/png', 'data' => self::PIXEL],
                ]],
            ],
        ])]);
    }

    private function payload(array $overrides = []): array
    {
        return ['prompt' => 'A bottle on a wooden table', 'aspect_ratio' => '4:5', 'image_size' => '2K', 'references' => [], ...$overrides];
    }

    public function test_the_page_shows_products_and_who_can_do_what(): void
    {
        $brand = $this->makeBrand('Prime Z');

        $this->actingAs($this->makeUser('marketing_manager', $brand))->get('/studio')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Studio/Index')
            ->where('connection.connected', false)
            ->has('products', 6)
            ->where('canGenerate', true)
            ->where('canManage', true)
        );

        $this->actingAs($this->makeUser('creative', $brand))->get('/studio')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canGenerate', true)
            ->where('canManage', false)
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

    public function test_connecting_checks_the_key_keeps_it_encrypted_and_never_shows_it(): void
    {
        $manager = $this->makeUser('marketing_manager', $this->makeBrand('Prime Z'));

        Http::fake(['*/v1beta/models/*' => Http::response(['error' => ['message' => 'API key not valid.']], 400)]);
        $this->actingAs($manager)->post('/studio/connection', ['api_key' => 'wrong-key-wrong-key-wrong'])->assertSessionHasErrors('api_key');
        $this->assertFalse(GeminiCredentials::isConnected());

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*/v1beta/models/*' => Http::response(['name' => 'models/x'])]);
        $this->actingAs($manager)->post('/studio/connection', ['api_key' => self::KEY])->assertSessionHasNoErrors();

        $this->assertSame(self::KEY, GeminiCredentials::key());
        $this->assertStringNotContainsString(self::KEY, (string) Storage::disk('local')->get('gemini-credentials.json'));

        $response = $this->actingAs($manager)->get('/studio')->assertInertia(fn (Assert $page) => $page
            ->where('connection.connected', true)
            ->where('connection.hint', 'abcd')
        );
        $this->assertStringNotContainsString(self::KEY, (string) $response->getContent());

        $this->actingAs($manager)->delete('/studio/connection');
        $this->assertFalse(GeminiCredentials::isConnected());
    }

    public function test_only_people_with_edit_access_can_connect(): void
    {
        $creative = $this->makeUser('creative', $this->makeBrand('Prime Z'));

        $this->actingAs($creative)->post('/studio/connection', ['api_key' => self::KEY])->assertForbidden();
        $this->actingAs($creative)->delete('/studio/connection')->assertForbidden();
    }

    public function test_generating_sends_the_prompt_size_and_references_and_saves_the_image(): void
    {
        GeminiCredentials::save(self::KEY, 'Admin');
        $this->fakeImage();
        $brand = $this->makeBrand('Prime Z');
        $user = $this->makeUser('creative', $brand);

        $this->actingAs($user)->post('/studio/references', ['file' => UploadedFile::fake()->image('pose.jpg'), 'kind' => 'reference'])->assertSessionHasNoErrors();
        $upload = StudioReference::query()->sole();

        $this->actingAs($user)->post('/studio/generate', $this->payload(['references' => ['product:cleanse', $upload->key()]]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('studio.index', ['image' => StudioGeneration::query()->sole()->id]));

        Http::assertSent(function (HttpRequest $request) {
            $body = $request->data();

            return str_ends_with($request->url(), '/v1beta/interactions')
                && $request->hasHeader('x-goog-api-key', self::KEY)
                && $body['model'] === 'gemini-nano-banana-2.1'
                && $body['response_format'] === ['type' => 'image', 'aspect_ratio' => '4:5', 'image_size' => '2K']
                && $body['input'][0] === ['type' => 'text', 'text' => 'A bottle on a wooden table']
                && count($body['input']) === 3
                && $body['input'][1]['type'] === 'image'
                && $body['input'][1]['mime_type'] === 'image/jpeg';
        });

        $generation = StudioGeneration::query()->sole();
        $this->assertSame(['product:cleanse', $upload->key()], $generation->reference_keys);
        $this->assertSame('image/png', $generation->mime_type);
        $this->assertSame(1, $generation->width);
        Storage::disk('local')->assertExists($generation->path);

        $this->actingAs($user)->get('/studio/images/'.$generation->id)->assertOk();
        $this->actingAs($user)->get('/studio/images/'.$generation->id.'/download')->assertOk();
    }

    public function test_changing_an_image_sends_that_image_first_and_keeps_its_references(): void
    {
        GeminiCredentials::save(self::KEY, 'Admin');
        $this->fakeImage();
        $user = $this->makeUser('creative', $this->makeBrand('Prime Z'));

        $this->actingAs($user)->post('/studio/generate', $this->payload(['references' => ['product:soap']]))->assertSessionHasNoErrors();
        $first = StudioGeneration::query()->sole();

        Http::fake(function () {
            return Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'image', 'data' => self::PIXEL]]]]]);
        });

        $this->actingAs($user)->post('/studio/generate', $this->payload(['prompt' => 'Make the background lighter', 'parent_id' => $first->id, 'references' => []]))->assertSessionHasNoErrors();

        Http::assertSent(function (HttpRequest $request) {
            $input = $request->data()['input'] ?? [];

            return count($input) === 3
                && str_contains($input[0]['text'], 'Make the background lighter')
                && str_contains($input[0]['text'], 'keep everything else the same')
                && $input[1]['data'] === self::PIXEL;
        });

        $second = StudioGeneration::query()->latest('id')->first();
        $this->assertSame($first->id, $second->parent_id);
        $this->assertSame('Make the background lighter', $second->prompt);
        $this->assertSame(['product:soap'], $second->reference_keys);
    }

    public function test_problems_from_google_are_explained_and_save_nothing(): void
    {
        GeminiCredentials::save(self::KEY, 'Admin');
        $user = $this->makeUser('creative', $this->makeBrand('Prime Z'));

        foreach ([
            [Http::response(['error' => ['message' => 'Quota exceeded']], 429), 'billing'],
            [Http::response(['error' => ['message' => 'bad']], 403), 'rejected this API key'],
            [Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'I cannot make that.']]]]]), 'I cannot make that.'],
            [Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'image', 'data' => base64_encode('not an image')]]]]]), 'could not read'],
        ] as [$answer, $expected]) {
            Http::fake(['*' => $answer]);
            $response = $this->actingAs($user)->post('/studio/generate', $this->payload());
            $response->assertSessionHasErrors('generate');
            $this->assertStringContainsString($expected, session('errors')->first('generate'));
            Http::swap(new \Illuminate\Http\Client\Factory);
        }

        $this->assertSame(0, StudioGeneration::query()->count());
    }

    public function test_nothing_is_sent_without_a_connection_or_with_bad_input(): void
    {
        Http::fake();
        $user = $this->makeUser('creative', $this->makeBrand('Prime Z'));

        $this->actingAs($user)->post('/studio/generate', $this->payload())->assertSessionHasErrors('generate');

        GeminiCredentials::save(self::KEY, 'Admin');
        $this->actingAs($user)->post('/studio/generate', $this->payload(['prompt' => '']))->assertSessionHasErrors('prompt');
        $this->actingAs($user)->post('/studio/generate', $this->payload(['aspect_ratio' => '99:1']))->assertSessionHasErrors('aspect_ratio');
        $this->actingAs($user)->post('/studio/generate', $this->payload(['image_size' => '1k']))->assertSessionHasErrors('image_size');
        $this->actingAs($user)->post('/studio/generate', $this->payload(['references' => ['product:nothing']]))->assertSessionHasErrors('references');
        $this->actingAs($user)->post('/studio/generate', $this->payload(['references' => ['a', 'b', 'c', 'd', 'e', 'f', 'g']]))->assertSessionHasErrors('references');

        Http::assertNothingSent();
    }

    public function test_uploads_must_be_images_and_stay_within_the_brand(): void
    {
        GeminiCredentials::save(self::KEY, 'Admin');
        Http::fake();
        $brand = $this->makeBrand('Prime Z');
        $user = $this->makeUser('creative', $brand);
        $outsider = $this->makeUser('creative', $this->makeBrand('Other Brand'));

        $this->actingAs($user)->post('/studio/references', ['file' => UploadedFile::fake()->create('script.pdf', 10, 'application/pdf'), 'kind' => 'reference'])->assertSessionHasErrors('file');
        $this->actingAs($user)->post('/studio/references', ['file' => UploadedFile::fake()->image('bottle.png'), 'kind' => 'product'])->assertSessionHasNoErrors();

        $reference = StudioReference::query()->sole();
        $this->assertSame('product', $reference->kind);
        $this->assertSame('bottle', $reference->name);

        $this->actingAs($outsider)->get('/studio/references/'.$reference->id.'/image')->assertForbidden();
        $this->actingAs($outsider)->delete('/studio/references/'.$reference->id)->assertForbidden();
        $this->actingAs($outsider)->post('/studio/generate', $this->payload(['references' => [$reference->key()]]))->assertSessionHasErrors('references');
        Http::assertNothingSent();

        $this->actingAs($user)->delete('/studio/references/'.$reference->id)->assertRedirect();
        Storage::disk('local')->assertMissing($reference->path);
    }

    public function test_images_saved_during_the_flow_version_still_open_and_can_be_changed(): void
    {
        GeminiCredentials::save(self::KEY, 'Admin');
        $this->fakeImage();
        $brand = $this->makeBrand('Prime Z');
        $user = $this->makeUser('creative', $brand);

        Storage::disk('local')->put('studio/generated/flow.png', (string) base64_decode(self::PIXEL));
        $saved = StudioGeneration::query()->create([
            'brand_id' => $brand->id, 'user_id' => $user->id, 'prompt' => 'Made in Flow', 'aspect_ratio' => '4:5',
            'reference_keys' => [], 'path' => 'studio/generated/flow.png', 'mime_type' => 'image/png', 'size' => 10,
        ]);

        $this->actingAs($user)->get('/studio?image='.$saved->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $saved->id)
            ->where('selected.image_size', null)
        );

        $this->actingAs($user)->post('/studio/generate', $this->payload(['prompt' => 'Lighter', 'parent_id' => $saved->id, 'image_size' => '1K']))->assertSessionHasNoErrors();
        $this->assertSame($saved->id, StudioGeneration::query()->latest('id')->first()->parent_id);
    }

    public function test_the_update_gives_managers_back_the_right_to_connect_google(): void
    {
        $this->seedOperations();
        $role = Role::query()->where('slug', 'marketing_manager')->sole();
        $role->permissions()->detach(Permission::query()->where('slug', 'studio.update')->pluck('id'));
        $creative = Role::query()->where('slug', 'creative')->sole();

        (require database_path('migrations/2026_11_03_150000_restore_studio_gemini.php'))->restorePermission();

        $this->assertTrue($role->permissions()->where('slug', 'studio.update')->exists());
        $this->assertFalse($creative->permissions()->where('slug', 'studio.update')->exists());
    }

    public function test_deleting_an_image_removes_its_file(): void
    {
        GeminiCredentials::save(self::KEY, 'Admin');
        $this->fakeImage();
        $brand = $this->makeBrand('Prime Z');
        $owner = $this->makeUser('creative', $brand);
        $colleague = $this->makeUser('creative', $brand);

        $this->actingAs($owner)->post('/studio/generate', $this->payload());
        $generation = StudioGeneration::query()->sole();

        $this->actingAs($colleague)->delete('/studio/images/'.$generation->id)->assertForbidden();
        $this->actingAs($owner)->delete('/studio/images/'.$generation->id)->assertRedirect(route('studio.index'));

        $this->assertSame(0, StudioGeneration::query()->count());
        Storage::disk('local')->assertMissing($generation->path);
    }
}
