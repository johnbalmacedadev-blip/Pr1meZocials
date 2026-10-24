<?php

namespace Tests\Feature;

use App\Models\PermissionPreset;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
    }

    public function test_manager_can_update_role_matrix_and_save_a_preset(): void
    {
        $brand = $this->makeBrand('Northwind');
        $manager = $this->makeUser('marketing_manager', $brand);
        $creative = Role::query()->where('slug', 'creative')->firstOrFail();

        $this->actingAs($manager)->get('/team')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Team/Index')
            ->where('canManageRoles', true)
            ->has('pageAccess.pages')
            ->has('presets')
        );

        $this->actingAs($manager)->put('/team/roles/'.$creative->id.'/permissions', [
            'matrix' => [
                'content' => ['view', 'create'],
                'inspiration' => ['view'],
                'resources' => ['view'],
                'board' => ['view'],
                'calendar' => ['view'],
                'posting_schedule' => ['view'],
                'posting' => [],
                'campaigns' => [],
                'team' => ['view'],
                'performance' => ['view'],
                'settings' => [],
            ],
            'extras' => ['content.view.any', 'comments.create'],
        ])->assertRedirect()->assertSessionHas('success');

        $creative->load('permissions');
        $slugs = $creative->permissions->pluck('slug')->all();
        $this->assertContains('content.view', $slugs);
        $this->assertContains('board.view', $slugs);
        $this->assertNotContains('campaigns.view', $slugs);
        $this->assertNotContains('settings.view', $slugs);

        $this->actingAs($manager)->post('/team/permission-presets', [
            'name' => 'Creative light',
            'matrix' => [
                'content' => ['view'],
                'board' => ['view'],
                'campaigns' => [],
                'settings' => [],
            ],
            'extras' => ['content.view.any'],
        ])->assertRedirect()->assertSessionHas('success');

        $preset = PermissionPreset::query()->where('name', 'Creative light')->firstOrFail();
        $this->assertFalse($preset->is_system);

        $this->actingAs($manager)->post('/team/roles/'.$creative->id.'/presets/'.$preset->id)
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_creative_default_excludes_settings_and_campaigns(): void
    {
        $brand = $this->makeBrand();
        $creative = $this->makeUser('creative', $brand);

        $this->assertTrue($creative->hasPermission('content.view'));
        $this->assertTrue($creative->hasPermission('board.view'));
        $this->assertTrue($creative->hasPermission('calendar.view'));
        $this->assertTrue($creative->hasPermission('posting_schedule.view'));
        $this->assertTrue($creative->hasPermission('performance.view'));
        $this->assertTrue($creative->hasPermission('team.view'));
        $this->assertFalse($creative->hasPermission('campaigns.view'));
        $this->assertFalse($creative->hasPermission('settings.view'));
        $this->assertFalse($creative->hasPermission('settings.manage'));
        $this->assertFalse($creative->hasPermission('posting.create'));
    }

    public function test_permission_catalog_seed_does_not_wipe_saved_role_matrix(): void
    {
        $brand = $this->makeBrand();
        $manager = $this->makeUser('marketing_manager', $brand);
        $creative = Role::query()->where('slug', 'creative')->firstOrFail();

        $this->actingAs($manager)->put('/team/roles/'.$creative->id.'/permissions', [
            'matrix' => [
                'content' => ['view'],
                'board' => ['view'],
                'campaigns' => ['view'],
                'settings' => [],
            ],
            'extras' => ['content.view.any'],
        ])->assertRedirect();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionCatalogSeeder', '--force' => true]);

        $creative->load('permissions');
        $slugs = $creative->permissions->pluck('slug')->all();
        $this->assertContains('campaigns.view', $slugs);
        $this->assertContains('content.view', $slugs);
        $this->assertNotContains('settings.view', $slugs);
    }
}
