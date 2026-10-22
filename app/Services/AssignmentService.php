<?php

namespace App\Services;

use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function __construct(
        private ActivityLogger $activity,
        private NotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function sync(ContentItem $item, array $data, User $actor): ContentItem
    {
        return DB::transaction(function () use ($item, $data, $actor) {
            $previous = [
                'owner_id' => $item->owner_id,
                'copywriter_id' => $item->copywriter_id,
                'video_editor_id' => $item->video_editor_id,
                'reviewer_id' => $item->reviewer_id,
            ];

            if (array_key_exists('owner_id', $data)) {
                $userId = $data['owner_id'] ? (int) $data['owner_id'] : null;

                if ($userId) {
                    $this->assertMember($item, $userId, 'owner_id');
                }

                $item->owner_id = $userId;
            }

            $item->copywriter_id = null;
            $item->video_editor_id = null;
            $item->reviewer_id = null;
            $item->save();

            if (array_key_exists('collaborator_ids', $data)) {
                $ids = collect($data['collaborator_ids'] ?? [])
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                foreach ($ids as $id) {
                    $this->assertMember($item, $id, 'collaborator_ids');
                }

                $item->collaborators()->sync(
                    $ids->mapWithKeys(fn (int $id) => [$id => ['assigned_by' => $actor->id]])->all()
                );
            }

            $item->load(['owner', 'copywriter', 'videoEditor', 'reviewer', 'collaborators']);

            $roles = [
                'owner_id' => ['owner', 'Task assigned'],
            ];

            foreach ($roles as $field => [$role, $title]) {
                $newId = $item->{$field};

                if ($newId && $newId !== $previous[$field] && $newId !== $actor->id) {
                    $person = $item->{$this->relationFor($field)};

                    if ($person) {
                        $this->notifications->send(
                            $person,
                            'assigned',
                            $title,
                            'You were assigned to '.$item->reference_number.' · '.$item->title.($item->deadline ? ' Deadline '.$item->deadline->toFormattedDateString().'.' : '.'),
                            'assigned:'.$item->id.':'.$person->id.':'.$role.':'.now()->toDateString(),
                            ['content_id' => $item->id, 'role' => $role],
                        );
                    }
                }
            }

            $this->activity->log($item, $actor, 'assigned', 'Assignments updated.', [
                'owner_id' => $item->owner_id,
                'copywriter_id' => $item->copywriter_id,
                'video_editor_id' => $item->video_editor_id,
                'reviewer_id' => $item->reviewer_id,
                'collaborator_ids' => $item->collaborators->pluck('id')->all(),
            ]);

            return $item;
        });
    }

    private function relationFor(string $field): string
    {
        return match ($field) {
            'owner_id' => 'owner',
            'copywriter_id' => 'copywriter',
            'video_editor_id' => 'videoEditor',
            'reviewer_id' => 'reviewer',
        };
    }

    private function assertMember(ContentItem $item, int $userId, string $field): void
    {
        $user = User::query()->find($userId);

        if (! $user || ! $user->is_active) {
            throw ValidationException::withMessages([
                $field => 'Choose an active teammate.',
            ]);
        }

        if (! $user->canAccessBrand($item->brand_id)) {
            $user->brands()->syncWithoutDetaching([$item->brand_id]);
        }
    }
}
