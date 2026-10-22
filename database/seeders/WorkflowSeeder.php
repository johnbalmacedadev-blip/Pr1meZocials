<?php

namespace Database\Seeders;

use App\Models\ContentItem;
use App\Models\WorkflowStatus;
use App\Support\Lookups;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['inbox', 'New Idea', 'slate', 1],
            ['ready_for_production', 'Ready for Production', 'violet', 2],
            ['in_progress', 'In Progress', 'amber', 3],
            ['internal_review', 'For Review', 'orange', 4],
            ['approved', 'Approved', 'emerald', 5],
            ['published', 'Published', 'teal', 6],
            ['archived', 'Archived', 'zinc', 7],
        ];

        $slugs = array_column($rows, 0);

        foreach ($rows as [$slug, $name, $color, $order]) {
            WorkflowStatus::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'color' => $color,
                'sort_order' => $order,
                'is_system' => true,
                'enter_permission' => null,
                'allowed_transitions' => array_values(array_filter($slugs, fn (string $candidate) => $candidate !== $slug)),
                'required_fields' => [],
            ]);
        }

        $map = [
            'ideation' => 'inbox',
            'ready_for_brief' => 'ready_for_production',
            'revision_required' => 'in_progress',
            'scheduled' => 'approved',
        ];

        foreach ($map as $from => $to) {
            $fromId = WorkflowStatus::query()->where('slug', $from)->value('id');
            $toId = WorkflowStatus::query()->where('slug', $to)->value('id');

            if ($fromId && $toId) {
                ContentItem::query()->where('status_id', $fromId)->update(['status_id' => $toId]);
            }
        }

        WorkflowStatus::query()->whereNotIn('slug', $slugs)->delete();
        Lookups::flush();
    }
}
