<?php

namespace App\Services;

use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ContentQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function make(User $user, array $filters = [], bool $sort = true): Builder
    {
        $query = ContentItem::query()->visibleTo($user);

        if (! empty($filters['trashed'])) {
            $query->onlyTrashed();
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', (int) $filters['brand_id']);
        }

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', (int) $filters['campaign_id']);
        }

        if (! empty($filters['status'])) {
            $query->whereHas('status', fn (Builder $status) => $status->where('slug', $filters['status']));
        }

        if (! empty($filters['format'])) {
            $query->where('format', $filters['format']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['distribution'])) {
            $query->where('distribution', $filters['distribution']);
        }

        if (! empty($filters['funnel_stage'])) {
            $query->where('funnel_stage', $filters['funnel_stage']);
        }

        if (! empty($filters['pillar_id'])) {
            $query->where('pillar_id', (int) $filters['pillar_id']);
        }

        if (! empty($filters['objective_id'])) {
            $query->where('objective_id', (int) $filters['objective_id']);
        }

        if (! empty($filters['owner']) && $filters['owner'] === 'me') {
            $query->where('owner_id', $user->id);
        } elseif (! empty($filters['owner_id'])) {
            $query->where('owner_id', (int) $filters['owner_id']);
        }

        if (! empty($filters['collaborator_id'])) {
            $id = (int) $filters['collaborator_id'];
            $query->where(function (Builder $inner) use ($id) {
                $inner->where('copywriter_id', $id)
                    ->orWhere('video_editor_id', $id)
                    ->orWhereHas('collaborators', fn (Builder $collaborators) => $collaborators->where('users.id', $id));
            });
        }

        if (! empty($filters['reviewer_id'])) {
            $query->where('reviewer_id', (int) $filters['reviewer_id']);
        }

        if (! empty($filters['platform_id'])) {
            $query->whereHas('platformVersions', fn (Builder $versions) => $versions->where('platform_id', (int) $filters['platform_id']));
        }

        if (! empty($filters['overdue'])) {
            $query->whereDate('deadline', '<', now()->toDateString())
                ->whereHas('status', fn (Builder $status) => $status->whereNotIn('slug', config('ops.closed_statuses')));
        }

        if (! empty($filters['deadline_from'])) {
            $query->whereDate('deadline', '>=', $filters['deadline_from']);
        }

        if (! empty($filters['deadline_to'])) {
            $query->whereDate('deadline', '<=', $filters['deadline_to']);
        }

        if (! empty($filters['publish_from']) || ! empty($filters['publish_to'])) {
            $query->whereHas('platformVersions', function (Builder $versions) use ($filters) {
                if (! empty($filters['publish_from'])) {
                    $versions->whereDate('scheduled_at', '>=', $filters['publish_from']);
                }
                if (! empty($filters['publish_to'])) {
                    $versions->whereDate('scheduled_at', '<=', $filters['publish_to']);
                }
            });
        }

        if (! empty($filters['q'])) {
            $term = '%'.addcslashes(trim((string) $filters['q']), '%_\\').'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('reference_number', 'like', $term)
                    ->orWhere('original_idea', 'like', $term)
                    ->orWhereHas('campaign', fn (Builder $campaign) => $campaign->where('name', 'like', $term))
                    ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', $term));
            });
        }

        if ($sort) {
            $column = in_array($filters['sort'] ?? '', ['updated_at', 'created_at', 'deadline', 'title', 'priority', 'published_at'], true)
                ? $filters['sort']
                : 'updated_at';
            $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

            if ($column === 'priority') {
                $query->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'medium' then 3 when 'low' then 4 else 5 end {$direction}");
            } else {
                $query->orderBy($column, $direction);
            }
        }

        return $query;
    }
}
