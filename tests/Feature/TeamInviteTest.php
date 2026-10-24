<?php

namespace Tests\Feature;

use App\Mail\AdminNewUserRegisteredMail;
use App\Mail\WelcomeNewUserMail;
use App\Models\Role;
use App\Models\TeamInvite;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
        Mail::fake();

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
        ])->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Join')
                ->where('registered', true)
                ->where('registeredName', 'New Teammate'));

        $this->assertAuthenticated();
        $person = User::query()->where('email', 'newmate@primez.test')->firstOrFail();
        $this->assertTrue($person->hasRole('creative'));
        $this->assertTrue($person->brands->contains('id', $brand->id));
        $this->assertSame(1, $invite->fresh()->uses_count);

        Mail::assertSent(WelcomeNewUserMail::class, fn (WelcomeNewUserMail $mail) => $mail->hasTo('newmate@primez.test'));
        Mail::assertSent(AdminNewUserRegisteredMail::class, fn (AdminNewUserRegisteredMail $mail) => $mail->hasTo($admin->email));

        $this->assertTrue(UserNotification::query()
            ->where('user_id', $admin->id)
            ->where('type', 'user_registered')
            ->where('dedupe_key', 'user-registered-'.$person->id)
            ->exists());
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
