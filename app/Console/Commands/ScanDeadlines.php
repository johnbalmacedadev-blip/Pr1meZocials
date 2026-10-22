<?php

namespace App\Console\Commands;

use App\Models\ContentItem;
use App\Models\Setting;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class ScanDeadlines extends Command
{
    protected $signature = 'ops:scan-deadlines';

    protected $description = 'Notify owners about approaching and overdue production deadlines';

    public function handle(NotificationService $notifications): int
    {
        $warningDays = (int) Setting::getValue('deadline_warning_days', config('ops.deadline_warning_days'));
        $closed = config('ops.closed_statuses');

        $soon = ContentItem::query()
            ->whereDate('deadline', '>=', now()->toDateString())
            ->whereDate('deadline', '<=', now()->addDays($warningDays)->toDateString())
            ->whereHas('status', fn ($query) => $query->whereNotIn('slug', $closed))
            ->with('owner')
            ->get();

        foreach ($soon as $item) {
            if (! $item->owner) {
                continue;
            }

            $notifications->send(
                $item->owner,
                'deadline_approaching',
                'Deadline approaching',
                $item->reference_number.' · '.$item->title.' is due '.$item->deadline->toFormattedDateString().'.',
                'due-soon:'.$item->id.':'.$item->deadline->toDateString(),
                ['content_id' => $item->id],
            );
        }

        $overdue = ContentItem::query()
            ->whereDate('deadline', '<', now()->toDateString())
            ->whereHas('status', fn ($query) => $query->whereNotIn('slug', $closed))
            ->with('owner')
            ->get();

        foreach ($overdue as $item) {
            if (! $item->owner) {
                continue;
            }

            $notifications->send(
                $item->owner,
                'overdue',
                'Content is overdue',
                $item->reference_number.' · '.$item->title.' missed its '.$item->deadline->toFormattedDateString().' deadline.',
                'overdue:'.$item->id.':'.$item->deadline->toDateString(),
                ['content_id' => $item->id],
            );
        }

        $this->info('Checked '.($soon->count() + $overdue->count()).' dated content items.');

        return self::SUCCESS;
    }
}
