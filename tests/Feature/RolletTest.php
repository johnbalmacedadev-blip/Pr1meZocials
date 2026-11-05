<?php

namespace Tests\Feature;

use App\Models\RolletSlip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class RolletTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
    }

    private function drawnName(): ?string
    {
        return RolletSlip::query()->whereNotNull('draw_token')->latest('updated_at')->value('name');
    }

    public function test_anyone_can_open_the_page_and_it_lists_every_name_without_saying_who_is_taken(): void
    {
        RolletSlip::query()->where('name', 'Sam')->update(['picked_by' => 'Secret Person', 'picked_at' => now()]);

        $response = $this->get('/rollet')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Rollet/Index')
            ->has('names', 17)
            ->where('names.0', 'Sam')
            ->where('names.16', 'Kyle')
            ->where('draw', null)
            ->where('allTaken', false)
            ->missing('results')
        );

        $this->assertStringNotContainsString('Secret Person', (string) $response->getContent());
    }

    public function test_the_login_page_stays_open_to_guests_and_rollet_needs_no_sign_in_to_draw(): void
    {
        $this->get('/login')->assertOk();
        $this->post('/rollet/draw')->assertRedirect(route('rollet.index'));
        $this->assertSame(1, RolletSlip::query()->whereNotNull('draw_token')->count());
    }

    public function test_a_draw_is_held_so_reloading_or_pressing_again_keeps_the_same_name(): void
    {
        $this->post('/rollet/draw');
        $name = $this->drawnName();
        $id = RolletSlip::query()->whereNotNull('draw_token')->value('id');

        $this->post('/rollet/draw');
        $this->assertSame($id, RolletSlip::query()->whereNotNull('draw_token')->sole()->id);

        $this->get('/rollet')->assertInertia(fn (Assert $page) => $page->where('draw', $name));
    }

    public function test_confirming_records_the_name_and_it_can_never_be_drawn_again_but_still_flashes(): void
    {
        $picked = [];
        for ($person = 0; $person < 17; $person++) {
            $this->post('/rollet/draw')->assertSessionHasNoErrors();
            $slip = RolletSlip::query()->whereNotNull('draw_token')->sole();
            $this->assertNotContains($slip->id, $picked, 'A confirmed name was drawn again.');
            $picked[] = $slip->id;

            $this->post('/rollet/confirm', ['picker_name' => 'Picker '.$person])->assertSessionHasNoErrors();
            $this->assertSame('Picker '.$person, $slip->fresh()->picked_by);
            $this->assertNotNull($slip->fresh()->picked_at);
            $this->assertNull($slip->fresh()->draw_token);
        }

        $this->assertCount(17, array_unique($picked));

        $this->post('/rollet/draw')->assertSessionHasErrors('rollet');
        $this->get('/rollet')->assertInertia(fn (Assert $page) => $page
            ->has('names', 17)
            ->where('allTaken', true)
            ->where('draw', null)
        );
    }

    public function test_someone_elses_held_name_cannot_be_drawn(): void
    {
        RolletSlip::query()->where('name', '!=', 'Sam')->update(['picked_by' => 'Done', 'picked_at' => now()]);
        RolletSlip::query()->where('name', 'Sam')->update(['draw_token' => 'someone-else', 'held_until' => now()->addMinutes(5)]);

        $this->post('/rollet/draw')->assertSessionHasErrors('rollet');
        $this->assertSame('someone-else', RolletSlip::query()->where('name', 'Sam')->value('draw_token'));

        // An abandoned hold runs out, and the name goes back in the draw.
        RolletSlip::query()->where('name', 'Sam')->update(['held_until' => now()->subMinute()]);
        $this->post('/rollet/draw')->assertSessionHasNoErrors();
        $this->assertNotSame('someone-else', RolletSlip::query()->where('name', 'Sam')->value('draw_token'));
    }

    public function test_a_name_is_required_and_confirming_without_a_draw_saves_nothing(): void
    {
        $this->post('/rollet/confirm', ['picker_name' => 'Nobody'])->assertSessionHasErrors('rollet');

        $this->post('/rollet/draw');
        $this->post('/rollet/confirm', ['picker_name' => ''])->assertSessionHasErrors('picker_name');
        $this->post('/rollet/confirm', ['picker_name' => '    '])->assertSessionHasErrors('picker_name');
        $this->assertSame(0, RolletSlip::query()->whereNotNull('picked_at')->count());
        $this->assertSame(1, RolletSlip::query()->whereNotNull('draw_token')->count());
    }

    public function test_drawing_your_own_name_puts_it_back_and_the_next_draw_is_someone_else(): void
    {
        $this->post('/rollet/draw');
        $slip = RolletSlip::query()->whereNotNull('draw_token')->sole();

        $this->post('/rollet/confirm', ['picker_name' => '  '.strtoupper($slip->name).' '])->assertSessionHasErrors('rollet');
        $this->assertNull($slip->fresh()->picked_at);
        $this->assertNull($slip->fresh()->draw_token);

        $this->post('/rollet/draw');
        $this->assertNotSame($slip->id, RolletSlip::query()->whereNotNull('draw_token')->sole()->id);
    }

    public function test_the_last_name_can_be_kept_even_if_it_is_your_own(): void
    {
        RolletSlip::query()->where('name', '!=', 'Sam')->update(['picked_by' => 'Done', 'picked_at' => now()]);

        $this->post('/rollet/draw');
        $this->post('/rollet/confirm', ['picker_name' => 'Sam'])->assertSessionHasNoErrors();
        $this->assertSame('Sam', RolletSlip::query()->where('name', 'Sam')->value('picked_by'));
    }

    public function test_the_same_person_cannot_pick_twice(): void
    {
        $this->post('/rollet/draw');
        $this->post('/rollet/confirm', ['picker_name' => 'Zed Tester'])->assertSessionHasNoErrors();

        $this->post('/rollet/draw');
        $slip = RolletSlip::query()->whereNotNull('draw_token')->sole();
        $this->post('/rollet/confirm', ['picker_name' => 'zed tester'])->assertSessionHasErrors('picker_name');

        $this->assertNull($slip->fresh()->picked_at);
        $this->assertNotNull($slip->fresh()->draw_token);
        $this->post('/rollet/confirm', ['picker_name' => 'Zed Tester Two'])->assertSessionHasNoErrors();
    }

    public function test_results_are_for_people_with_access_and_a_name_can_be_put_back(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('marketing_manager', $brand);
        $creative = $this->makeUser('creative', $brand);

        $this->post('/rollet/draw');
        $slip = RolletSlip::query()->whereNotNull('draw_token')->sole();
        $this->post('/rollet/confirm', ['picker_name' => 'Zed Tester']);

        $this->get('/rollet-results')->assertRedirect();
        $this->actingAs($creative)->get('/rollet-results')->assertForbidden();
        $this->actingAs($creative)->delete('/rollet-results/'.$slip->id)->assertForbidden();

        $this->actingAs($manager)->get('/rollet-results')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Rollet/Results')
            ->has('results', 1)
            ->where('results.0.name', $slip->name)
            ->where('results.0.picked_by', 'Zed Tester')
            ->has('remaining', 16)
            ->where('total', 17)
            ->where('canRemove', true)
        );

        $this->actingAs($manager)->delete('/rollet-results/'.$slip->id)->assertRedirect();
        $this->assertNull($slip->fresh()->picked_by);
        $this->actingAs($manager)->get('/rollet-results')->assertInertia(fn (Assert $page) => $page->has('results', 0)->has('remaining', 17));
    }
}
