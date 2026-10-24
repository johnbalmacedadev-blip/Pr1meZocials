<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\TeamInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class TeamInviteTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
    }

    public function test_admin_can_create_invite_and_guest_can_register(): void
    {
        $brand = $this->makeBrand();
        $admin = $this->makeUser('administrator', $brand);
        $creative = Role::query()->where('slug', 'creative')->firstOrFail();

        $this->actingAs($admin)
            ->post('/settings/invites', [
                'role_id' => $creative->id,
                'brand_ids' => [$brand->id],
                'expires_in_days' => 7,
                'max_uses' => 5,
            ])
            ->assertRedirect();

        $invite = TeamInvite::query()->latest('id')->firstOrFail();
        $this->assertTrue($invite->isOpen());

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();

        $this->post('/join/'.$invite->token, [
            'name' => 'New Teammate',
            'username' => 'newmate',
            'email' => 'newmate@primez.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $person = User::query()->where('email', 'newmate@primez.test')->firstOrFail();
        $this->assertTrue($person->hasRole('creative'));
        $this->assertTrue($person->brands->contains('id', $brand->id));
        $this->assertSame(1, $invite->fresh()->uses_count);
    }

    public function test_revoked_invite_cannot_be_used(): void
    {
        $brand = $this->makeBrand();
        $admin = $this->makeUser('administrator', $brand);
        $creative = Role::query()->where('slug', 'creative')->firstOrFail();

        $invite = TeamInvite::query()->create([
            'token' => 'revoked-token-example-123456789012345678901234',
            'role_id' => $creative->id,
            'brand_ids' => [$brand->id],
            'created_by' => $admin->id,
            'expires_at' => now()->addDays(7),
            'max_uses' => 5,
            'uses_count' => 0,
        ]);

        $this->actingAs($admin)->delete('/settings/invites/'.$invite->id)->assertRedirect();
        $this->post('/logout');

        $this->get('/join/'.$invite->token)->assertNotFound();
    }
}
