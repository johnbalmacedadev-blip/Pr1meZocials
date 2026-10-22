<?php

namespace App\Services;

use App\Models\ContentItem;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentWriter
{
    public function __construct(
        private ReferenceNumberGenerator $references,
        private ActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): ContentItem
    {
        return retry(3, function () use ($data, $actor) {
            return DB::transaction(function () use ($data, $actor) {
                $item = new ContentItem(Arr::only($data, (new ContentItem)->getFillable()));
                $item->created_by = $actor->id;
                $item->reference_number = $this->references->next();
                $item->save();
                $this->syncTags($item, $data['tag_ids'] ?? []);
                $this->activity->log($item, $actor, 'created', 'Idea captured.', [
                    'format' => $item->format,
                    'brand_id' => $item->brand_id,
                ]);

                return $item;
            });
        }, 30, fn ($exception) => $exception instanceof UniqueConstraintViolationException);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ContentItem $item, array $data, User $actor): ContentItem
    {
        return DB::transaction(function () use ($item, $data, $actor) {
            $fillable = (new ContentItem)->getFillable();

            if (! $actor->hasPermission('content.update.any')) {
                $fillable = array_values(array_diff($fillable, [
                    'brand_id', 'campaign_id', 'pillar_id', 'objective_id', 'audience_segment_id',
                    'funnel_stage', 'priority', 'distribution', 'deadline', 'target_production_date',
                    'hypothesis', 'success_metric', 'scheduled_publish_at',
                ]));
            }

            $before = $item->only($fillable);
            $item->fill(Arr::only($data, $fillable));
            $changes = [];

            foreach ($item->getDirty() as $field => $value) {
                $changes[$field] = [
                    'from' => $this->scalar($before[$field] ?? null),
                    'to' => $this->scalar($value),
                ];
            }

            $item->save();

            if (array_key_exists('tag_ids', $data) && $actor->hasPermission('content.update.any')) {
                $this->syncTags($item, $data['tag_ids'] ?? []);
            } elseif (array_key_exists('tag_ids', $data)) {
                $this->syncTags($item, $data['tag_ids'] ?? []);
            }

            if ($changes !== []) {
                $this->activity->log($item, $actor, 'updated', 'Content details updated.', ['changes' => $changes]);
            }

            return $item->fresh();
        });
    }

    /**
     * @param  array<int, mixed>  $tagIds
     */
    private function syncTags(ContentItem $item, array $tagIds): void
    {
        $ids = collect($tagIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            $item->tags()->sync([]);

            return;
        }

        $valid = Tag::query()
            ->whereIn('id', $ids)
            ->where(function ($query) use ($item) {
                $query->whereNull('brand_id')->orWhere('brand_id', $item->brand_id);
            })
            ->pluck('id');

        if ($valid->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'tag_ids' => 'One or more tags do not belong to this brand.',
            ]);
        }

        $item->tags()->sync($valid->all());
    }

    private function scalar(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value;
    }
}
