<?php

namespace Database\Seeders;

use App\Models\WorkflowStatus;
use App\Support\Lookups;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['inbox', 'Inbox / New Idea', 'slate', 1, null, ['ideation', 'ready_for_brief', 'archived'], []],
            ['ideation', 'Ideation', 'sky', 2, null, ['inbox', 'ready_for_brief', 'archived'], []],
            ['ready_for_brief', 'Ready for Brief', 'indigo', 3, null, ['ideation', 'ready_for_production', 'archived'], []],
            ['ready_for_production', 'Ready for Production', 'violet', 4, null, ['ready_for_brief', 'in_progress', 'archived'], ['owner_id', 'deadline']],
            ['in_progress', 'In Progress', 'amber', 5, null, ['ready_for_production', 'internal_review', 'archived'], ['owner_id']],
            ['internal_review', 'Internal Review', 'orange', 6, null, ['in_progress', 'revision_required', 'approved', 'archived'], ['owner_id']],
            ['revision_required', 'Revision Required', 'rose', 7, 'content.approve', ['in_progress', 'internal_review', 'archived'], []],
            ['approved', 'Approved', 'emerald', 8, 'content.approve', ['internal_review', 'scheduled', 'published', 'archived'], []],
            ['scheduled', 'Scheduled', 'cyan', 9, 'content.publish', ['approved', 'published', 'archived'], []],
            ['published', 'Published', 'teal', 10, 'content.publish', ['scheduled', 'archived'], []],
            ['archived', 'Archived', 'zinc', 11, 'content.archive', ['inbox'], []],
        ];

        foreach ($rows as [$slug, $name, $color, $order, $permission, $transitions, $required]) {
            WorkflowStatus::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'color' => $color,
                'sort_order' => $order,
                'is_system' => true,
                'enter_permission' => $permission,
                'allowed_transitions' => $transitions,
                'required_fields' => $required,
            ]);
        }

        Lookups::flush();
    }
}
