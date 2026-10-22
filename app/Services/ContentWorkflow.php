<?php

namespace App\Services;

use App\Models\ContentApproval;
use App\Models\ContentItem;
use App\Models\User;
use App\Models\WorkflowStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentWorkflow
{
    public function __construct(
        private ActivityLogger $activity,
        private NotificationService $notifications,
    ) {}

    public function transition(
        ContentItem $item,
        string $toSlug,
        User $actor,
        ?string $note = null,
        bool $recordApproval = true,
    ): ContentItem {
        return DB::transaction(function () use ($item, $toSlug, $actor, $note, $recordApproval) {
            $item->loadMissing('status');
            $from = $item->status;
            $to = WorkflowStatus::query()->where('slug', $toSlug)->firstOrFail();

            if ($from && $from->id === $to->id) {
                return $item;
            }

            if (! $actor->hasPermission('content.transition')) {
                abort(403, 'You cannot change content status.');
            }

            $allowed = $from->allowed_transitions ?? [];

            if (! in_array($to->slug, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => "Content cannot move from {$from->name} to {$to->name}.",
                ]);
            }

            if ($to->enter_permission && ! $actor->hasPermission($to->enter_permission)) {
                throw ValidationException::withMessages([
                    'status' => "You do not have permission to move content to {$to->name}.",
                ]);
            }

            $this->assertRequiredFields($item, $to);
            $this->assertStageRules($item, $to, $note);

            $approval = null;

            if ($recordApproval && in_array($to->slug, ['approved', 'revision_required'], true)) {
                $approval = $this->recordDecision(
                    $item,
                    $actor,
                    $to->slug === 'approved' ? 'approved' : 'revision_requested',
                    $note,
                );
            }

            if ($to->slug === 'archived') {
                $item->archived_at = now();
            } elseif ($from->slug === 'archived') {
                $item->archived_at = null;
            }

            if ($to->slug === 'published' && $item->published_at === null) {
                $item->published_at = now();
            }

            $item->status_id = $to->id;
            $item->save();

            $activity = $this->activity->log(
                $item,
                $actor,
                'status_changed',
                "Status changed from {$from->name} to {$to->name}.",
                [
                    'from' => $from->slug,
                    'to' => $to->slug,
                    'note' => $note,
                    'approval_id' => $approval?->id,
                ],
            );

            $this->notify($item->fresh(['owner', 'reviewer', 'status']), $to->slug, $actor, $note, $approval, $activity->id);

            return $item->fresh(['status', 'owner', 'reviewer']);
        });
    }

    public function recordDecision(ContentItem $item, User $actor, string $decision, ?string $notes, ?int $assetId = null): ContentApproval
    {
        $submission = $item->approvals()->count() + 1;

        return $item->approvals()->create([
            'reviewer_id' => $actor->id,
            'decision' => $decision,
            'notes' => $notes,
            'creative_asset_id' => $assetId,
            'submission_number' => $submission,
        ]);
    }

    private function assertRequiredFields(ContentItem $item, WorkflowStatus $to): void
    {
        $labels = [
            'owner_id' => 'a primary owner',
            'deadline' => 'a production deadline',
            'reviewer_id' => 'a reviewer',
            'caption_draft' => 'a caption draft',
            'original_idea' => 'the original idea',
        ];

        foreach ($to->required_fields ?? [] as $field) {
            if (blank($item->{$field})) {
                $label = $labels[$field] ?? str_replace('_', ' ', $field);

                throw ValidationException::withMessages([
                    'status' => "Moving to {$to->name} requires {$label}.",
                ]);
            }
        }
    }

    private function assertStageRules(ContentItem $item, WorkflowStatus $to, ?string $note): void
    {
        if ($to->slug === 'internal_review' && ! $this->hasReviewSubstance($item)) {
            throw ValidationException::withMessages([
                'status' => 'Add a caption, concept, storyline, original idea, or uploaded asset before submitting for review.',
            ]);
        }

        if ($to->slug === 'revision_required' && blank($note)) {
            throw ValidationException::withMessages([
                'notes' => 'Explain what needs to change before requesting revisions.',
            ]);
        }

        if ($to->slug === 'scheduled') {
            $hasSchedule = $item->platformVersions()->whereNotNull('scheduled_at')->exists();

            if (! $hasSchedule) {
                throw ValidationException::withMessages([
                    'status' => 'Add a platform version with a publishing date before scheduling. This does not change the production deadline.',
                ]);
            }
        }

        if ($to->slug === 'published') {
            $hasUrl = $item->platformVersions()->whereNotNull('published_url')->where('published_url', '!=', '')->exists();

            if (! $hasUrl) {
                throw ValidationException::withMessages([
                    'status' => 'Record a published URL on a platform version before marking the content as published.',
                ]);
            }
        }
    }

    private function hasReviewSubstance(ContentItem $item): bool
    {
        return filled($item->caption_draft)
            || filled($item->storyline)
            || filled($item->original_idea)
            || filled($item->creative_concept)
            || $item->assets()->exists();
    }

    private function notify(ContentItem $item, string $toSlug, User $actor, ?string $note, ?ContentApproval $approval, int $activityId): void
    {
        $link = ['content_id' => $item->id];
        $eventKey = $approval?->id ?? $activityId;

        if ($toSlug === 'internal_review') {
            $recipients = $item->reviewer
                ? collect([$item->reviewer])
                : User::query()
                    ->where('is_active', true)
                    ->whereHas('brands', fn ($query) => $query->where('brands.id', $item->brand_id))
                    ->whereHas('roles.permissions', fn ($query) => $query->where('permissions.slug', 'content.approve'))
                    ->get();

            foreach ($recipients as $recipient) {
                if ($recipient->id === $actor->id) {
                    continue;
                }

                $this->notifications->send(
                    $recipient,
                    'submitted_for_review',
                    'Creative submitted for review',
                    $item->title.' is ready for review.',
                    'review-request:'.$item->id.':'.$eventKey,
                    $link,
                );
            }
        }

        if (in_array($toSlug, ['revision_required', 'approved'], true) && $item->owner && $item->owner->id !== $actor->id) {
            $isRevision = $toSlug === 'revision_required';

            $this->notifications->send(
                $item->owner,
                $isRevision ? 'revision_requested' : 'approved',
                $isRevision ? 'Revisions requested' : 'Creative approved',
                $isRevision
                    ? $item->title.' needs revisions.'.($note ? ' '.$note : '')
                    : $item->title.' was approved. Approval does not publish it.',
                ($isRevision ? 'revision:' : 'approved:').$item->id.':'.$eventKey,
                $link,
            );
        }
    }
}
