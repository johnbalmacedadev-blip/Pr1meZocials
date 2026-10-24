<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionPreset;
use App\Models\Role;
use App\Support\PageAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionCatalogSeeder::class);

        $all = Permission::query()->pluck('id', 'slug');

        $roles = [
            'administrator' => [
                'name' => 'Administrator',
                'description' => 'Manages users, brands, workflow, and every record.',
            ],
            'marketing_manager' => [
                'name' => 'Marketing Manager',
                'description' => 'Plans campaigns, assigns work, approves creative, and reads performance.',
            ],
            'social_media_manager' => [
                'name' => 'Social Media Manager',
                'description' => 'Builds ideas, coordinates platform versions, and schedules publishing.',
            ],
            'creative' => [
                'name' => 'Creative Team',
                'description' => 'Sees major brand pages (except Settings and Campaigns); produces assigned creative work.',
            ],
            'reviewer' => [
                'name' => 'Reviewer',
                'description' => 'Approves work or sends it back with a required explanation.',
            ],
        ];

        foreach ($roles as $slug => $role) {
            $model = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $role['name'], 'description' => $role['description']],
            );

            $defaults = PageAccess::defaultForRole($slug);
            $permissionSlugs = $slug === 'administrator'
                ? $all->keys()->all()
                : PageAccess::expand($defaults['matrix'], $defaults['extras']);

            $model->permissions()->sync($all->only($permissionSlugs)->values());

            if (Schema::hasTable('permission_presets')) {
                PermissionPreset::query()->updateOrCreate(
                    ['slug' => 'system-'.$slug],
                    [
                        'name' => $role['name'].' default',
                        'description' => $role['description'],
                        'matrix' => $defaults['matrix'],
                        'extras' => $defaults['extras'],
                        'is_system' => true,
                        'created_by' => null,
                    ],
                );
            }
        }
    }
}
