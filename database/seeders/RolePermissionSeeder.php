<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'users.view' => ['View team directory', 'Users'],
            'users.manage' => ['Manage users and roles', 'Users'],
            'settings.manage' => ['Manage system settings', 'Settings'],
            'brands.view' => ['View brands', 'Brands'],
            'brands.manage' => ['Create and edit brands', 'Brands'],
            'platforms.manage' => ['Manage social platforms', 'Platforms'],
            'workflow.manage' => ['Configure workflow statuses', 'Workflow'],
            'taxonomy.manage' => ['Manage pillars, objectives, and audiences', 'Taxonomy'],
            'tags.manage' => ['Manage tags', 'Taxonomy'],
            'campaigns.view' => ['View campaigns', 'Campaigns'],
            'campaigns.manage' => ['Manage campaigns', 'Campaigns'],
            'content.view' => ['View authorized content', 'Content'],
            'content.view.any' => ['View all content in assigned brands', 'Content'],
            'content.create' => ['Capture content ideas', 'Content'],
            'content.update' => ['Update assigned content', 'Content'],
            'content.update.any' => ['Update any content in assigned brands', 'Content'],
            'content.delete' => ['Move content to trash', 'Content'],
            'content.archive' => ['Archive content', 'Content'],
            'content.assign' => ['Assign owners and collaborators', 'Content'],
            'content.transition' => ['Change workflow status', 'Content'],
            'content.approve' => ['Approve or request revisions', 'Content'],
            'content.publish' => ['Schedule and record publication', 'Content'],
            'content.export' => ['Export content', 'Content'],
            'assets.view' => ['View creative assets', 'Assets'],
            'assets.upload' => ['Upload creative assets', 'Assets'],
            'assets.delete' => ['Delete creative assets', 'Assets'],
            'performance.view' => ['View performance reports', 'Performance'],
            'performance.manage' => ['Record performance manually', 'Performance'],
            'comments.create' => ['Comment on content', 'Collaboration'],
            'team.view' => ['View team workload', 'Team'],
            'reports.view' => ['View operational reports', 'Reports'],
        ];

        foreach ($definitions as $slug => [$name, $group]) {
            Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'group' => $group]);
        }

        $all = Permission::query()->pluck('id', 'slug');

        $roles = [
            'administrator' => [
                'name' => 'Administrator',
                'description' => 'Manages users, brands, workflow, and every record.',
                'permissions' => $all->keys()->all(),
            ],
            'marketing_manager' => [
                'name' => 'Marketing Manager',
                'description' => 'Plans campaigns, assigns work, approves creative, and reads performance.',
                'permissions' => [
                    'users.view', 'brands.view', 'brands.manage', 'taxonomy.manage', 'tags.manage',
                    'campaigns.view', 'campaigns.manage', 'content.view', 'content.view.any', 'content.create',
                    'content.update', 'content.update.any', 'content.delete', 'content.archive', 'content.assign',
                    'content.transition', 'content.approve', 'content.publish', 'content.export',
                    'assets.view', 'assets.upload', 'assets.delete', 'performance.view', 'performance.manage',
                    'comments.create', 'team.view', 'reports.view',
                ],
            ],
            'social_media_manager' => [
                'name' => 'Social Media Manager',
                'description' => 'Builds ideas, coordinates platform versions, and schedules publishing.',
                'permissions' => [
                    'users.view', 'brands.view', 'tags.manage', 'campaigns.view', 'campaigns.manage',
                    'content.view', 'content.view.any', 'content.create', 'content.update', 'content.update.any',
                    'content.archive', 'content.assign', 'content.transition', 'content.publish', 'content.export',
                    'assets.view', 'assets.upload', 'performance.view', 'performance.manage',
                    'comments.create', 'team.view', 'reports.view',
                ],
            ],
            'creative' => [
                'name' => 'Creative Team',
                'description' => 'Produces assigned creative work and submits it for review.',
                'permissions' => [
                    'brands.view', 'campaigns.view', 'content.view', 'content.create', 'content.update',
                    'content.transition', 'assets.view', 'assets.upload', 'performance.view',
                    'comments.create', 'reports.view',
                ],
            ],
            'reviewer' => [
                'name' => 'Reviewer',
                'description' => 'Approves work or sends it back with a required explanation.',
                'permissions' => [
                    'brands.view', 'campaigns.view', 'content.view', 'content.transition', 'content.approve',
                    'assets.view', 'comments.create', 'performance.view', 'reports.view',
                ],
            ],
        ];

        foreach ($roles as $slug => $role) {
            $model = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $role['name'], 'description' => $role['description']],
            );

            $model->permissions()->sync($all->only($role['permissions'])->values());
        }
    }
}
