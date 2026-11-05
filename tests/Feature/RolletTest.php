<?php

namespace Tests\Feature;

use App\Models\RolletSlip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
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
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function slipId(string $name, int $nth = 0): int
    {
        return RolletSlip::query()->where('name', $name)->orderBy('id')->pluck('id')[$nth];
    }

    /** A person arriving on their own browser. */
    private function asNewVisitor(): void
    {
        $this->flushSession();
    }

    private function heldBy(int $pickerId): RolletSlip
    {
        return RolletSlip::query()->where('picker_slip_id', $pickerId)->whereNull('picked_at')->sole();
    }

    private function pick(int $pickerId): RolletSlip
    {
        $this->asNewVisitor();
        $this->post('/rollet/draw', ['picker_id' => $pickerId])->assertSessionHasNoErrors();
        $slip = $this->heldBy($pickerId);
        $this->post('/rollet/confirm')->assertSessionHasNoErrors();

        return $slip->fresh();
    }

    public function test_anyone_can_open_the_page_and_it_lists_every_name_without_saying_which_are_taken(): void
    {
        RolletSlip::query()->whereKey($this->slipId('Sam'))->update(['picked_by' => 'Mami', 'picked_at' => now(), 'picker_slip_id' => $this->slipId('Mami')]);

        $this->get('/rollet')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Rollet/Index')
            ->has('names', 16)
            ->where('names.0', 'Sam')
            ->where('names.9', 'Kyle')
            ->where('names.15', 'Beloved john')
            ->where('draw', null)
            ->where('allTaken', false)
            ->missing('results')
            ->where('people', function ($people) {
                $labels = collect($people)->pluck('label');
                // Sam was drawn but has not picked yet, so Sam is still listed. Mami has picked.
                $this->assertContains('Sam', $labels);
                $this->assertNotContains('Mami', $labels);
                $this->assertContains('Kyle', $labels);
                $this->assertNotContains('Kyle 1', $labels);
                $this->assertCount(15, $labels);

                return true;
            })
        );
    }

    public function test_you_must_say_who_you_are_before_drawing(): void
    {
        $this->post('/rollet/draw')->assertSessionHasErrors('picker_id');
        $this->post('/rollet/draw', ['picker_id' => 99999])->assertSessionHasErrors('rollet');
        $this->assertSame(0, RolletSlip::query()->whereNotNull('draw_token')->count());
    }

    public function test_you_can_never_draw_your_own_name(): void
    {
        $sam = $this->slipId('Sam');

        for ($try = 0; $try < 40; $try++) {
            $this->asNewVisitor();
            $this->post('/rollet/draw', ['picker_id' => $sam])->assertSessionHasNoErrors();
            $slip = $this->heldBy($sam);
            $this->assertNotSame($sam, $slip->id);
            $slip->update(['draw_token' => null, 'held_until' => null, 'picker_slip_id' => null]);
        }
    }

    public function test_every_draw_ends_with_everyone_having_someone_else(): void
    {
        for ($round = 0; $round < 12; $round++) {
            RolletSlip::query()->update(['picked_by' => null, 'picked_at' => null, 'draw_token' => null, 'held_until' => null, 'picker_slip_id' => null]);

            foreach (RolletSlip::query()->pluck('id')->shuffle() as $pickerId) {
                $slip = $this->pick($pickerId);
                $this->assertNotSame($pickerId, $slip->id, 'Someone drew their own name.');
                $this->assertNotNull($slip->picked_at);
            }

            $this->assertSame(16, RolletSlip::query()->whereNotNull('picked_at')->distinct()->count('picker_slip_id'));
        }

        $this->asNewVisitor();
        $this->get('/rollet')->assertInertia(fn (Assert $page) => $page->where('allTaken', true)->has('people', 0)->has('names', 16));
    }

    public function test_with_two_names_left_the_next_person_cannot_leave_the_last_person_with_their_own(): void
    {
        [$sam, $mami] = [$this->slipId('Sam'), $this->slipId('Mami')];
        $others = RolletSlip::query()->whereNotIn('id', [$sam, $mami])->pluck('id')->values();

        // Everyone else has picked; Sam's and Liam's names are the ones left. Mami and Sam still have to pick.
        $liam = $this->slipId('Liam');
        $pickers = $others->reject(fn ($id) => $id === $liam)->values()->push($liam)->values();
        $names = $others->reject(fn ($id) => $id === $liam)->values()->push($mami)->values();
        foreach ($names as $index => $slipId) {
            RolletSlip::query()->whereKey($slipId)->update(['picked_by' => 'Done', 'picked_at' => now(), 'picker_slip_id' => $pickers[$index]]);
        }

        for ($try = 0; $try < 25; $try++) {
            $this->asNewVisitor();
            $this->post('/rollet/draw', ['picker_id' => $mami])->assertSessionHasNoErrors();
            $slip = $this->heldBy($mami);
            $this->assertSame($sam, $slip->id, 'Mami had to draw Sam, or Sam would be left with only Sam.');
            $slip->update(['draw_token' => null, 'held_until' => null, 'picker_slip_id' => null]);
        }
    }

    public function test_a_draw_is_held_so_reloading_or_submitting_again_keeps_the_same_name(): void
    {
        $sam = $this->slipId('Sam');
        $this->post('/rollet/draw', ['picker_id' => $sam]);
        $slip = $this->heldBy($sam);

        $this->post('/rollet/draw', ['picker_id' => $sam]);
        $this->assertSame($slip->id, $this->heldBy($sam)->id);

        $this->get('/rollet')->assertInertia(fn (Assert $page) => $page
            ->where('draw.name', RolletSlip::labels()[$slip->id])
            ->where('draw.picker', 'Sam')
        );
    }

    public function test_confirming_saves_it_and_that_name_and_person_are_done(): void
    {
        $sam = $this->slipId('Sam');
        $slip = $this->pick($sam);

        $this->assertSame('Sam', $slip->picked_by);
        $this->assertNull($slip->draw_token);

        $this->asNewVisitor();
        $this->post('/rollet/draw', ['picker_id' => $sam])->assertSessionHasErrors('rollet');

        for ($try = 0; $try < 20; $try++) {
            $this->asNewVisitor();
            $picker = $this->slipId('Mami');
            $this->post('/rollet/draw', ['picker_id' => $picker])->assertSessionHasNoErrors();
            $held = $this->heldBy($picker);
            $this->assertNotSame($slip->id, $held->id, 'A confirmed name was drawn again.');
            $held->update(['draw_token' => null, 'held_until' => null, 'picker_slip_id' => null]);
        }
    }

    public function test_someone_already_picking_on_another_device_cannot_start_again_until_it_expires(): void
    {
        $sam = $this->slipId('Sam');
        $this->post('/rollet/draw', ['picker_id' => $sam])->assertSessionHasNoErrors();
        $held = $this->heldBy($sam);

        $this->asNewVisitor();
        $this->post('/rollet/draw', ['picker_id' => $sam])->assertSessionHasErrors('rollet');
        $this->get('/rollet')->assertInertia(fn (Assert $page) => $page
            ->where('people', fn ($people) => ! collect($people)->pluck('label')->contains('Sam'))
        );

        $held->update(['held_until' => now()->subMinute()]);
        $this->post('/rollet/draw', ['picker_id' => $sam])->assertSessionHasNoErrors();
    }

    public function test_confirming_without_a_draw_saves_nothing(): void
    {
        $this->post('/rollet/confirm')->assertSessionHasErrors('rollet');
        $this->assertSame(0, RolletSlip::query()->whereNotNull('picked_at')->count());
    }

    public function test_picks_typed_before_this_update_are_linked_to_their_person(): void
    {
        RolletSlip::query()->whereKey($this->slipId('Sam'))->update(['picked_by' => 'mami', 'picked_at' => now()]);
        RolletSlip::query()->whereKey($this->slipId('Liam'))->update(['picked_by' => 'Someone New', 'picked_at' => now()]);

        $migration = require database_path('migrations/2026_11_05_110000_add_picker_to_rollet_slips.php');
        $migration->down();
        $migration->up();

        $this->assertSame($this->slipId('Mami'), RolletSlip::query()->whereKey($this->slipId('Sam'))->value('picker_slip_id'));
        $this->assertNull(RolletSlip::query()->whereKey($this->slipId('Liam'))->value('picker_slip_id'));
    }

    private function secondKyle(): array
    {
        $first = $this->slipId('Kyle');
        $second = RolletSlip::query()->create(['name' => 'Kyle'])->id;

        return [$first, $second];
    }

    private function removeDuplicateKyle(): void
    {
        (require database_path('migrations/2026_11_06_100000_remove_duplicate_kyle_from_rollet.php'))->up();

        $this->assertSame(1, RolletSlip::query()->where('name', 'Kyle')->count());
    }

    private function confirmed(int $slipId, int $pickerId, string $label): void
    {
        RolletSlip::query()->whereKey($slipId)->update(['picked_by' => $label, 'picked_at' => now(), 'picker_slip_id' => $pickerId]);
    }

    public function test_there_is_one_kyle_and_he_needs_no_number(): void
    {
        $this->assertSame(1, RolletSlip::query()->where('name', 'Kyle')->count());
        $this->assertSame(16, RolletSlip::query()->count());
        $this->assertSame('Kyle', RolletSlip::labels()[$this->slipId('Kyle')]);
    }

    public function test_removing_kyle_with_no_picks_keeps_the_first(): void
    {
        [$first, $second] = $this->secondKyle();

        $this->removeDuplicateKyle();

        $this->assertTrue(RolletSlip::query()->whereKey($first)->exists());
        $this->assertFalse(RolletSlip::query()->whereKey($second)->exists());
    }

    public function test_removing_kyle_keeps_the_one_someone_already_drew(): void
    {
        [$first, $second] = $this->secondKyle();
        $this->confirmed($second, $this->slipId('Sam'), 'Sam');

        $this->removeDuplicateKyle();

        $this->assertFalse(RolletSlip::query()->whereKey($first)->exists());
        $this->assertSame('Sam', RolletSlip::query()->whereKey($second)->value('picked_by'));
    }

    public function test_if_two_people_drew_a_kyle_the_second_person_picks_again(): void
    {
        [$first, $second] = $this->secondKyle();
        [$sam, $mami] = [$this->slipId('Sam'), $this->slipId('Mami')];
        $this->confirmed($first, $sam, 'Sam');
        $this->confirmed($second, $mami, 'Mami');

        $this->removeDuplicateKyle();

        $this->assertSame('Sam', RolletSlip::query()->whereKey($first)->value('picked_by'));
        $this->assertFalse(RolletSlip::query()->where('picker_slip_id', $mami)->exists());
        $this->get('/rollet')->assertInertia(fn (Assert $page) => $page
            ->where('people', fn ($people) => collect($people)->pluck('label')->contains('Mami') && ! collect($people)->pluck('label')->contains('Sam'))
        );
    }

    public function test_a_pick_made_by_the_removed_kyle_becomes_kyles(): void
    {
        [$first, $second] = $this->secondKyle();
        $neng = $this->slipId('Neng');
        $this->confirmed($neng, $second, 'Kyle 2');

        $this->removeDuplicateKyle();

        $slip = RolletSlip::query()->find($neng);
        $this->assertSame('Kyle', $slip->picked_by);
        $this->assertSame($first, $slip->picker_slip_id);
    }

    public function test_if_both_kyles_picked_only_one_pick_stays_and_the_other_name_returns(): void
    {
        [$first, $second] = $this->secondKyle();
        [$neng, $liam] = [$this->slipId('Neng'), $this->slipId('Liam')];
        $this->confirmed($neng, $first, 'Kyle 1');
        $this->confirmed($liam, $second, 'Kyle 2');

        $this->removeDuplicateKyle();

        $this->assertSame('Kyle', RolletSlip::query()->whereKey($neng)->value('picked_by'));
        $this->assertNull(RolletSlip::query()->whereKey($liam)->value('picked_at'));
    }

    public function test_if_one_kyle_drew_the_other_he_picks_again_instead_of_keeping_his_own_name(): void
    {
        [$first, $second] = $this->secondKyle();
        $this->confirmed($second, $first, 'Kyle 1');

        $this->removeDuplicateKyle();

        $kyle = RolletSlip::query()->where('name', 'Kyle')->sole();
        $this->assertNull($kyle->picked_at);
        $this->assertNull($kyle->picker_slip_id);
    }

    public function test_results_are_for_people_with_access_and_a_name_can_be_put_back(): void
    {
        $brand = $this->makeBrand('Prime Z');
        $manager = $this->makeUser('marketing_manager', $brand);
        $creative = $this->makeUser('creative', $brand);
        $sam = $this->slipId('Sam');
        $slip = $this->pick($sam);

        $this->get('/rollet-results')->assertRedirect();
        $this->actingAs($creative)->get('/rollet-results')->assertForbidden();
        $this->actingAs($creative)->delete('/rollet-results/'.$slip->id)->assertForbidden();

        $this->actingAs($manager)->get('/rollet-results')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Rollet/Results')
            ->has('results', 1)
            ->where('results.0.name', RolletSlip::labels()[$slip->id])
            ->where('results.0.picked_by', 'Sam')
            ->has('remaining', 15)
            ->has('waiting', 15)
            ->where('total', 16)
            ->where('canRemove', true)
        );

        $this->actingAs($manager)->delete('/rollet-results/'.$slip->id)->assertRedirect();
        $this->assertNull($slip->fresh()->picked_by);
        $this->assertNull($slip->fresh()->picker_slip_id);
        $this->actingAs($manager)->get('/rollet-results')->assertInertia(fn (Assert $page) => $page->has('results', 0)->has('remaining', 16)->has('waiting', 16));
    }
}
