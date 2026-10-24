<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionPreset;
use App\Models\Role;
use App\Support\PageAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures permission rows and system presets exist without resetting role assignments.
 */
class PermissionCatalogSeeder extends Seeder
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

        foreach (PageAccess::pages() as $page) {
            foreach ($page['actions'] as $action) {
                $slug = PageAccess::slug($page['key'], $action);
                $label = (PageAccess::actionLabels()[$action] ?? ucfirst($action)).' '.$page['label'];
                $definitions[$slug] = [$label, 'Pages'];
            }
        }

        foreach ($definitions as $slug => [$name, $group]) {
            Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'group' => $group]);
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_presets')) {
            return;
        }

        $roles = [
            'administrator' => 'Administrator',
            'marketing_manager' => 'Marketing Manager',
            'social_media_manager' => 'Social Media Manager',
            'creative' => 'Creative Team',
            'reviewer' => 'Reviewer',
        ];

        foreach ($roles as $slug => $name) {
            $role = Role::query()->where('slug', $slug)->first();
            if (! $role) {
                continue;
            }

            $defaults = PageAccess::defaultForRole($slug);
            PermissionPreset::query()->updateOrCreate(
                ['slug' => 'system-'.$slug],
                [
                    'name' => $name.' default',
                    'description' => $role->description,
                    'matrix' => $defaults['matrix'],
                    'extras' => $defaults['extras'],
                    'is_system' => true,
                    'created_by' => null,
                ],
            );
        }
    }
}
