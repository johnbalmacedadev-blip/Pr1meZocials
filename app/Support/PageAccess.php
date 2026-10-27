<?php

namespace App\Support;

/**
 * Page-level access matrix used by Team → Role permissions.
 * Each page action maps to a primary slug plus legacy capability slugs.
 */
class PageAccess
{
    /**
     * @return list<array{key: string, label: string, actions: list<string>}>
     */
    public static function pages(): array
    {
        return [
            ['key' => 'content', 'label' => 'Content Ideas', 'actions' => ['view', 'create', 'update', 'delete']],
            ['key' => 'inspiration', 'label' => 'Inspiration', 'actions' => ['view', 'create', 'update', 'delete']],
            ['key' => 'resources', 'label' => 'Creative Assets', 'actions' => ['view', 'create', 'update', 'delete']],
            ['key' => 'board', 'label' => 'Production Board', 'actions' => ['view']],
            ['key' => 'calendar', 'label' => 'Content Calendar', 'actions' => ['view', 'update']],
            ['key' => 'events', 'label' => 'Events Calendar', 'actions' => ['view', 'create', 'update', 'delete']],
            ['key' => 'posting_schedule', 'label' => 'Posting Schedule', 'actions' => ['view', 'create', 'delete']],
            ['key' => 'posting', 'label' => 'Posting', 'actions' => ['view', 'create', 'update', 'delete']],
            ['key' => 'campaigns', 'label' => 'Campaigns', 'actions' => ['view', 'create', 'update', 'delete']],
            ['key' => 'team', 'label' => 'Team', 'actions' => ['view', 'create', 'update', 'delete']],
            ['key' => 'performance', 'label' => 'Performance', 'actions' => ['view', 'create', 'delete']],
            ['key' => 'settings', 'label' => 'Settings', 'actions' => ['view', 'update']],
        ];
    }

    /**
     * Extra toggles that are not a simple page CRUD cell.
     *
     * @return list<array{slug: string, label: string, hint: string}>
     */
    public static function extras(): array
    {
        return [
            ['slug' => 'content.view.any', 'label' => 'View all brand content', 'hint' => 'See every idea in assigned brands, not only assigned work.'],
            ['slug' => 'content.update.any', 'label' => 'Edit any brand content', 'hint' => 'Update records without being the assignee.'],
            ['slug' => 'content.transition', 'label' => 'Change workflow status', 'hint' => 'Move cards between board columns.'],
            ['slug' => 'content.approve', 'label' => 'Approve / request revisions', 'hint' => 'Reviewer decisions on internal review.'],
            ['slug' => 'content.assign', 'label' => 'Assign owners & collaborators', 'hint' => 'Reassign work on the board and content pages.'],
            ['slug' => 'content.export', 'label' => 'Export content', 'hint' => 'Download content lists.'],
            ['slug' => 'comments.create', 'label' => 'Comment on content', 'hint' => 'Leave notes on content records.'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return ['view' => 'View', 'create' => 'Add', 'update' => 'Edit', 'delete' => 'Delete'];
    }

    public static function slug(string $page, string $action): string
    {
        return $page.'.'.$action;
    }

    /**
     * Expand a matrix selection into permission slugs stored on the role.
     *
     * @param  array<string, list<string>>  $matrix  page => actions
     * @param  list<string>  $extras
     * @return list<string>
     */
    public static function expand(array $matrix, array $extras = []): array
    {
        $slugs = [];

        foreach (self::pages() as $page) {
            $actions = array_values(array_intersect($page['actions'], $matrix[$page['key']] ?? []));
            foreach ($actions as $action) {
                $slugs[] = self::slug($page['key'], $action);
                foreach (self::legacyFor($page['key'], $action) as $legacy) {
                    $slugs[] = $legacy;
                }
            }
        }

        $allowedExtras = array_column(self::extras(), 'slug');
        foreach ($extras as $extra) {
            if (in_array($extra, $allowedExtras, true)) {
                $slugs[] = $extra;
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Legacy / capability slugs implied by a page action.
     *
     * @return list<string>
     */
    public static function legacyFor(string $page, string $action): array
    {
        return match ($page.'.'.$action) {
            'content.view' => ['content.view'],
            'content.create' => ['content.create'],
            'content.update' => ['content.update'],
            'content.delete' => ['content.delete', 'content.archive'],
            'inspiration.view' => ['content.view'],
            'inspiration.create' => ['content.create'],
            'inspiration.update' => ['content.update'],
            'inspiration.delete' => ['content.delete'],
            'resources.view' => ['assets.view'],
            'resources.create' => ['assets.upload', 'assets.view'],
            'resources.update' => ['assets.upload', 'assets.view'],
            'resources.delete' => ['assets.delete'],
            'board.view' => ['content.view'],
            'calendar.view' => ['content.view'],
            'calendar.update' => ['content.publish'],
            'posting_schedule.view' => ['content.view'],
            'posting_schedule.create' => ['content.publish'],
            'posting_schedule.delete' => ['content.publish'],
            'posting.view' => ['content.publish'],
            'posting.create' => ['content.publish'],
            'posting.update' => ['content.publish'],
            'posting.delete' => ['content.publish'],
            'campaigns.view' => ['campaigns.view'],
            'campaigns.create', 'campaigns.update', 'campaigns.delete' => ['campaigns.view', 'campaigns.manage'],
            'team.view' => ['team.view'],
            'team.create', 'team.update', 'team.delete' => ['team.view', 'users.view', 'users.manage'],
            'performance.view' => ['performance.view', 'reports.view'],
            'performance.create', 'performance.delete' => ['performance.view', 'performance.manage', 'reports.view'],
            'settings.view' => [
                'settings.manage', 'brands.view', 'users.view',
            ],
            'settings.update' => [
                'settings.manage', 'brands.view', 'brands.manage', 'users.view', 'users.manage',
                'platforms.manage', 'workflow.manage', 'taxonomy.manage', 'tags.manage',
            ],
            default => [],
        };
    }

    /**
     * When checking $slug, also accept these stored slugs (backward compatible).
     *
     * @return list<string>
     */
    public static function acceptedAs(string $slug): array
    {
        return match ($slug) {
            'board.view' => ['board.view', 'content.view', 'content.view.any'],
            'calendar.view' => ['calendar.view', 'content.view', 'content.view.any'],
            'calendar.update' => ['calendar.update', 'content.publish'],
            'posting_schedule.view' => ['posting_schedule.view', 'content.view', 'content.view.any'],
            'posting_schedule.create' => ['posting_schedule.create', 'content.publish'],
            'posting_schedule.delete' => ['posting_schedule.delete', 'content.publish'],
            'inspiration.view' => ['inspiration.view', 'content.view', 'content.view.any'],
            'inspiration.create' => ['inspiration.create', 'content.create'],
            'inspiration.update' => ['inspiration.update', 'content.update', 'content.update.any'],
            'inspiration.delete' => ['inspiration.delete', 'content.delete'],
            'resources.view' => ['resources.view', 'assets.view', 'content.view', 'content.view.any'],
            'resources.create' => ['resources.create', 'assets.upload'],
            'resources.update' => ['resources.update', 'assets.upload'],
            'resources.delete' => ['resources.delete', 'assets.delete'],
            'posting.view' => ['posting.view', 'content.publish'],
            'posting.create' => ['posting.create', 'content.publish'],
            'posting.update' => ['posting.update', 'content.publish'],
            'posting.delete' => ['posting.delete', 'content.publish'],
            'content.publish' => ['content.publish', 'posting.create', 'posting.view', 'posting.update', 'posting.delete'],
            'campaigns.manage' => ['campaigns.manage', 'campaigns.create', 'campaigns.update', 'campaigns.delete'],
            'settings.manage' => ['settings.manage', 'settings.view', 'settings.update'],
            'users.manage' => ['users.manage', 'team.create', 'team.update', 'team.delete', 'settings.update'],
            'performance.manage' => ['performance.manage', 'performance.create', 'performance.delete'],
            'assets.view' => ['assets.view', 'resources.view'],
            'assets.upload' => ['assets.upload', 'resources.create', 'resources.update'],
            'assets.delete' => ['assets.delete', 'resources.delete'],
            default => [$slug],
        };
    }

    /**
     * Decode stored permission slugs back into a matrix + extras for the UI.
     *
     * @param  list<string>  $slugs
     * @return array{matrix: array<string, list<string>>, extras: list<string>}
     */
    public static function decode(array $slugs): array
    {
        $set = array_fill_keys($slugs, true);
        $matrix = [];

        foreach (self::pages() as $page) {
            $matrix[$page['key']] = [];
            foreach ($page['actions'] as $action) {
                $primary = self::slug($page['key'], $action);
                if (isset($set[$primary]) || self::legacyImplies($set, $page['key'], $action)) {
                    $matrix[$page['key']][] = $action;
                }
            }
        }

        $extras = [];
        foreach (self::extras() as $extra) {
            if (isset($set[$extra['slug']])) {
                $extras[] = $extra['slug'];
            }
        }

        return ['matrix' => $matrix, 'extras' => $extras];
    }

    /**
     * @param  array<string, true>  $set
     */
    private static function legacyImplies(array $set, string $page, string $action): bool
    {
        // Only treat legacy capability slugs as page checks when the primary page slug is absent,
        // and only for pages whose action maps 1:1 to that capability (avoid marking every content
        // surface checked just because content.view exists).
        return match ($page.'.'.$action) {
            'content.view' => isset($set['content.view']) || isset($set['content.view.any']),
            'content.create' => isset($set['content.create']),
            'content.update' => isset($set['content.update']) || isset($set['content.update.any']),
            'content.delete' => isset($set['content.delete']),
            'campaigns.view' => isset($set['campaigns.view']),
            'campaigns.create', 'campaigns.update', 'campaigns.delete' => isset($set['campaigns.manage']),
            'team.view' => isset($set['team.view']),
            'team.create', 'team.update', 'team.delete' => isset($set['users.manage']),
            'performance.view' => isset($set['performance.view']) || isset($set['reports.view']),
            'performance.create', 'performance.delete' => isset($set['performance.manage']),
            'settings.view' => isset($set['settings.manage']),
            'settings.update' => isset($set['settings.manage']) && isset($set['brands.manage']),
            default => false,
        };
    }

    /**
     * Built-in role matrices (defaults).
     *
     * @return array{matrix: array<string, list<string>>, extras: list<string>}
     */
    public static function defaultForRole(string $roleSlug): array
    {
        return match ($roleSlug) {
            'administrator' => self::fullAccess(),
            'marketing_manager' => [
                'matrix' => [
                    'content' => ['view', 'create', 'update', 'delete'],
                    'inspiration' => ['view', 'create', 'update', 'delete'],
                    'resources' => ['view', 'create', 'update', 'delete'],
                    'board' => ['view'],
                    'calendar' => ['view', 'update'],
                    'events' => ['view', 'create', 'update', 'delete'],
                    'posting_schedule' => ['view', 'create', 'delete'],
                    'posting' => ['view', 'create', 'update', 'delete'],
                    'campaigns' => ['view', 'create', 'update', 'delete'],
                    'team' => ['view', 'create', 'update', 'delete'],
                    'performance' => ['view', 'create', 'delete'],
                    'settings' => ['view', 'update'],
                ],
                'extras' => [
                    'content.view.any', 'content.update.any', 'content.transition', 'content.approve',
                    'content.assign', 'content.export', 'comments.create',
                ],
            ],
            'social_media_manager' => [
                'matrix' => [
                    'content' => ['view', 'create', 'update', 'delete'],
                    'inspiration' => ['view', 'create', 'update', 'delete'],
                    'resources' => ['view', 'create', 'update'],
                    'board' => ['view'],
                    'calendar' => ['view', 'update'],
                    'events' => ['view', 'create', 'update', 'delete'],
                    'posting_schedule' => ['view', 'create', 'delete'],
                    'posting' => ['view', 'create', 'update', 'delete'],
                    'campaigns' => ['view', 'create', 'update', 'delete'],
                    'team' => ['view', 'create', 'update'],
                    'performance' => ['view', 'create', 'delete'],
                    'settings' => [],
                ],
                'extras' => [
                    'content.view.any', 'content.update.any', 'content.transition',
                    'content.assign', 'content.export', 'comments.create',
                ],
            ],
            'creative' => [
                'matrix' => [
                    'content' => ['view', 'create', 'update'],
                    'inspiration' => ['view', 'create', 'update'],
                    'resources' => ['view', 'create', 'update'],
                    'board' => ['view'],
                    'calendar' => ['view'],
                    'events' => ['view', 'create', 'update'],
                    'posting_schedule' => ['view'],
                    'posting' => [],
                    'campaigns' => [],
                    'team' => ['view'],
                    'performance' => ['view'],
                    'settings' => [],
                ],
                'extras' => [
                    'content.view.any', 'content.transition', 'comments.create',
                ],
            ],
            'reviewer' => [
                'matrix' => [
                    'content' => ['view'],
                    'inspiration' => ['view'],
                    'resources' => ['view'],
                    'board' => ['view'],
                    'calendar' => ['view'],
                    'events' => ['view'],
                    'posting_schedule' => ['view'],
                    'posting' => [],
                    'campaigns' => ['view'],
                    'team' => [],
                    'performance' => ['view'],
                    'settings' => [],
                ],
                'extras' => [
                    'content.view.any', 'content.transition', 'content.approve', 'comments.create',
                ],
            ],
            default => ['matrix' => [], 'extras' => []],
        };
    }

    /**
     * @return array{matrix: array<string, list<string>>, extras: list<string>}
     */
    public static function fullAccess(): array
    {
        $matrix = [];
        foreach (self::pages() as $page) {
            $matrix[$page['key']] = $page['actions'];
        }

        return [
            'matrix' => $matrix,
            'extras' => array_column(self::extras(), 'slug'),
        ];
    }
}
