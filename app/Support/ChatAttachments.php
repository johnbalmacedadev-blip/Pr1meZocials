<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\ContentItem;
use App\Models\Event;
use App\Models\InspirationItem;
use App\Models\ResourceLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Items from other features that can be shared into a chat message.
 * A shared item is stored as a snapshot so it still renders later.
 */
class ChatAttachments
{
    /**
     * @return array<string, string>
     */
    public static function types(User $user): array
    {
        $can = fn (string ...$permissions) => collect($permissions)->contains(fn ($slug) => $user->hasPermission($slug));

        return array_filter([
            'content' => $can('content.view', 'content.view.any') ? 'Task' : null,
            'event' => $can('events.view') ? 'Event' : null,
            'inspiration' => $can('inspiration.view', 'content.view', 'content.view.any', 'content.create') ? 'Inspiration' : null,
            'resource' => $can('resources.view', 'assets.view', 'content.view', 'content.view.any', 'content.create') ? 'Creative asset' : null,
            'campaign' => $can('campaigns.view') ? 'Campaign' : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function search(User $user, string $type, string $term): array
    {
        if (! array_key_exists($type, self::types($user))) {
            return [];
        }

        $brandIds = $user->accessibleBrandIds() ?: [0];
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query = match ($type) {
            'content' => ContentItem::query()
                ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->where('title', 'like', $like)->orWhere('reference_number', 'like', $like))),
            'event' => Event::query()->when($term !== '', fn (Builder $q) => $q->where('title', 'like', $like)),
            'inspiration' => InspirationItem::query()->whereNull('archived_at')
                ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->where('name', 'like', $like)->orWhere('url', 'like', $like))),
            'resource' => ResourceLink::query()->when($term !== '', fn (Builder $q) => $q->where('name', 'like', $like)),
            'campaign' => Campaign::query()->when($term !== '', fn (Builder $q) => $q->where('name', 'like', $like)),
        };

        return $query
            ->whereIn('brand_id', $brandIds)
            ->with('brand:id,name')
            ->latest('id')
            ->limit(40)
            ->get()
            ->filter(fn (Model $item) => $type !== 'content' || $user->can('view', $item))
            ->take(15)
            ->map(fn (Model $item) => self::snapshot($type, $item))
            ->values()
            ->all();
    }

    /**
     * Snapshot of one item, or null when it does not exist or the user cannot see it.
     *
     * @return array<string, mixed>|null
     */
    public static function resolve(User $user, string $type, int $id): ?array
    {
        if (! array_key_exists($type, self::types($user))) {
            return null;
        }

        $item = match ($type) {
            'content' => ContentItem::query()->find($id),
            'event' => Event::query()->find($id),
            'inspiration' => InspirationItem::query()->find($id),
            'resource' => ResourceLink::query()->find($id),
            'campaign' => Campaign::query()->find($id),
        };

        if (! $item || ! $user->canAccessBrand((int) $item->brand_id)) {
            return null;
        }

        if ($type === 'content' && ! $user->can('view', $item)) {
            return null;
        }

        return self::snapshot($type, $item->loadMissing('brand:id,name'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshot(string $type, Model $item): array
    {
        $brand = $item->brand?->name;

        [$title, $details, $url, $external] = match ($type) {
            'content' => [$item->title, [$item->reference_number], '/content/'.$item->id, false],
            'event' => [$item->title, [$item->starts_on?->toFormattedDateString(), $item->recurrenceLabel()], '/events/'.$item->id, false],
            'inspiration' => [$item->name ?: $item->url, [parse_url($item->url, PHP_URL_HOST) ?: 'Link'], $item->url, true],
            'resource' => [$item->name, ['Creative asset folder'], $item->url, true],
            'campaign' => [$item->name, [$item->status ? ucfirst((string) $item->status) : 'Campaign'], '/campaigns/'.$item->id, false],
        };

        return [
            'type' => $type,
            'id' => $item->id,
            // Scrub so legacy text with invalid UTF-8 cannot break the JSON response.
            'title' => mb_scrub((string) $title, 'UTF-8'),
            'subtitle' => mb_scrub(implode(' · ', array_filter([...$details, $brand], fn ($part) => filled($part))), 'UTF-8'),
            'url' => $url,
            'external' => $external,
        ];
    }
}
