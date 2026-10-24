<?php

namespace App\Support;

use App\Models\SocialPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FacebookCalendarEvents
{
    /**
     * Scheduled and published Facebook posts in a date range (same source as Posting Schedule).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function inRange(User $user, Carbon $start, Carbon $end): Collection
    {
        $visiblePageIds = $user->visibleSocialAccountIds();

        return SocialPost::query()
            ->with(['account:id,name,brand_id', 'user:id,name', 'contentItem:id,title,reference_number'])
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($scheduled) use ($start, $end) {
                    $scheduled->where('status', 'scheduled')
                        ->whereNotNull('scheduled_publish_at')
                        ->whereBetween('scheduled_publish_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
                })->orWhere(function ($published) use ($start, $end) {
                    $published->where('status', 'published')
                        ->whereNotNull('published_at')
                        ->whereBetween('published_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
                });
            })
            ->when(
                ! $user->isAdmin(),
                fn ($query) => $query->whereIn('social_account_id', $visiblePageIds ?: [0])
            )
            ->orderByRaw('COALESCE(scheduled_publish_at, published_at) asc')
            ->get()
            ->map(fn (SocialPost $post) => self::present($post))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(SocialPost $post): array
    {
        $at = $post->scheduled_publish_at ?? $post->published_at;
        $scheduled = $post->status === 'scheduled';

        return [
            'id' => 'facebook-'.$post->id,
            'social_post_id' => $post->id,
            'version_id' => null,
            'content_id' => $post->content_item_id,
            'href' => '/posting?tab=history&post='.$post->id,
            'title' => $post->contentItem?->title
                ?: Str::limit((string) $post->caption, 48)
                ?: 'Facebook post',
            'reference_number' => $post->contentItem?->reference_number ?: 'FB-'.$post->id,
            'date' => $at?->toDateString(),
            'time' => $at?->format('H:i'),
            'kind' => 'facebook',
            'format' => $post->media_type,
            'format_label' => $post->media_type === 'video' ? 'Facebook video' : 'Facebook poster',
            'platform' => ['id' => null, 'name' => 'Facebook', 'slug' => 'facebook', 'color' => 'sky'],
            'platform_name' => 'Facebook',
            'status' => [
                'name' => $scheduled ? 'Scheduled on Facebook' : 'Posted on Facebook',
                'slug' => $scheduled ? 'scheduled' : 'published',
                'color' => $scheduled ? 'sky' : 'emerald',
            ],
            'brand' => $post->account?->name,
            'owner' => $post->user ? ['id' => $post->user->id, 'name' => $post->user->name] : null,
            'publishing_status' => $post->status,
            'conflict' => false,
            'deadline' => null,
        ];
    }
}
