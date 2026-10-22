<?php

namespace App\Services;

use App\Models\ContentPlatformVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishingService
{
    public function __construct(
        private ActivityLogger $activity,
        private NotificationService $notifications,
        private ContentWorkflow $workflow,
    ) {}

    public function reschedule(ContentPlatformVersion $version, User $actor, Carbon $scheduledAt): ContentPlatformVersion
    {
        return DB::transaction(function () use ($version, $actor, $scheduledAt) {
            $version->load('contentItem');
            $item = $version->contentItem;
            $deadline = $item->deadline?->toDateString();
            $previous = $version->scheduled_at?->toIso8601String();

            $version->scheduled_at = $scheduledAt;

            if (in_array($version->publishing_status, ['draft', 'ready'], true)) {
                $version->publishing_status = 'scheduled';
            }

            $version->save();

            $earliest = $item->platformVersions()->whereNotNull('scheduled_at')->min('scheduled_at');
            $item->scheduled_publish_at = $earliest;
            $item->save();
            $item->refresh();

            if ($item->deadline?->toDateString() !== $deadline) {
                throw ValidationException::withMessages([
                    'scheduled_at' => 'Rescheduling must not change the production deadline.',
                ]);
            }

            $this->activity->log($item, $actor, 'rescheduled', 'Publishing date updated for '.$version->platform->name.'.', [
                'version_id' => $version->id,
                'from' => $previous,
                'to' => $scheduledAt->toIso8601String(),
                'deadline' => $deadline,
            ]);

            if ($item->owner && $item->owner_id !== $actor->id) {
                $this->notifications->send(
                    $item->owner,
                    'schedule_changed',
                    'Publishing date changed',
                    $item->title.' is now scheduled for '.$scheduledAt->toFormattedDateString().'. The production deadline is unchanged.',
                    'schedule:'.$version->id.':'.$scheduledAt->toDateString(),
                    ['content_id' => $item->id, 'version_id' => $version->id],
                );
            }

            return $version->fresh('platform');
        });
    }

    public function publish(
        ContentPlatformVersion $version,
        User $actor,
        string $url,
        ?Carbon $publishedAt = null,
        ?string $notes = null,
        bool $markContentPublished = true,
    ): ContentPlatformVersion {
        return DB::transaction(function () use ($version, $actor, $url, $publishedAt, $notes, $markContentPublished) {
            $version->load('contentItem.status', 'platform');
            $item = $version->contentItem;
            $deadline = $item->deadline?->toDateString();

            if (! in_array($item->status->slug, ['approved', 'scheduled', 'published'], true)) {
                throw ValidationException::withMessages([
                    'published_url' => 'Approve the content before recording it as published. Approval and publication are separate steps.',
                ]);
            }

            $when = $publishedAt ?? now();
            $version->published_url = $url;
            $version->published_at = $when;
            $version->publishing_status = 'published';
            $version->save();

            $version->publishingRecords()->create([
                'published_by' => $actor->id,
                'published_url' => $url,
                'published_at' => $when,
                'notes' => $notes,
            ]);

            if ($item->published_at === null) {
                $item->published_at = $when;
                $item->save();
            }

            if ($markContentPublished && $item->status->slug !== 'published') {
                if ($item->status->slug === 'approved') {
                    $hasSchedule = $item->platformVersions()->whereNotNull('scheduled_at')->exists();

                    if (! $hasSchedule) {
                        $version->scheduled_at ??= $when;
                        $version->save();
                    }

                    $this->workflow->transition($item->fresh('status'), 'scheduled', $actor, 'Scheduled while recording publication.');
                    $item = $item->fresh('status');
                }

                $this->workflow->transition($item, 'published', $actor, $notes);
            }

            $item->refresh();

            if ($item->deadline?->toDateString() !== $deadline) {
                throw ValidationException::withMessages([
                    'published_url' => 'Publishing must not change the production deadline.',
                ]);
            }

            $this->activity->log($item, $actor, 'published', 'Published on '.$version->platform->name.'.', [
                'version_id' => $version->id,
                'published_url' => $url,
                'published_at' => $when->toIso8601String(),
            ]);

            return $version->fresh(['platform', 'publishingRecords']);
        });
    }
}
