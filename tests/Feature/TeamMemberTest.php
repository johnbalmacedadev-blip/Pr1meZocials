<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class TeamMemberTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
    }

    public function test_a_manager_can_add_edit_and_remove_a_team_member(): void
    {
        $brand = $this->makeBrand('Northwind');
        $manager = $this->makeUser('social_media_manager', $brand);
        $member = $this->makeUser('creative', $brand, [
            'name' => 'Casey Brooks',
            'email' => 'casey@example.com',
            'username' => 'casey',
        ]);

        $this->actingAs($manager)->post('/team', [
            'name' => 'New Editor',
            'username' => 'editor',
            'email' => 'editor@example.com',
            'password' => 'password',
            'job_title' => 'Video editor',
            'role_ids' => [$member->roles()->first()->id],
        ])->assertRedirect()->assertSessionHas('success');

        $created = User::query()->where('email', 'editor@example.com')->firstOrFail();
        $this->assertSame('New Editor', $created->name);
        $this->assertTrue($created->brands()->whereKey($brand->id)->exists());

        $this->actingAs($manager)->put('/team/'.$member->id, [
            'name' => 'Casey Updated',
            'username' => 'casey',
            'email' => 'casey@example.com',
            'password' => '',
            'job_title' => 'Lead editor',
            'role_ids' => [$member->roles()->first()->id],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'name' => 'Casey Updated',
            'job_title' => 'Lead editor',
        ]);

        $this->actingAs($manager)->delete('/team/'.$member->id)->assertRedirect()->assertSessionHas('success');
        $this->assertSoftDeleted('users', ['id' => $member->id]);
    }

    public function test_a_creative_signing_in_from_the_team_page_lands_on_the_dashboard(): void
    {
        $brand = $this->makeBrand('Northwind');
        $creative = $this->makeUser('creative', $brand, [
            'name' => 'Casey Brooks',
            'email' => 'casey@example.com',
        ]);

        $this->withSession(['url.intended' => url('/team')])
            ->post('/login', [
                'email' => 'casey@example.com',
                'password' => 'password',
            ])
            ->assertRedirect('/dashboard');

        $this->actingAs($creative)->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page->component('Dashboard'));
        $this->actingAs($creative)->get('/team')->assertRedirect('/dashboard');
        $this->actingAs($creative)->get('/team/'.$creative->id)->assertRedirect('/dashboard');
    }

    public function test_a_manager_cannot_remove_their_own_account(): void
    {
        $brand = $this->makeBrand('Northwind');
        $manager = $this->makeUser('social_media_manager', $brand);

        $this->actingAs($manager)->delete('/team/'.$manager->id)->assertRedirect()->assertSessionHas('error');
        $this->assertNotSoftDeleted('users', ['id' => $manager->id]);
    }
}
