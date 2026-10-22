<?php

namespace App\Support;

use App\Models\ContentItem;
use App\Models\ContentPlatformVersion;
use App\Models\CreativeAsset;
use App\Models\PerformanceReport;
use App\Models\User;
use App\Services\PerformanceCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ContentPresenter
{
    public function __construct(private PerformanceCalculator $calculator) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(ContentItem $item): array
    {
        return [
            'id' => $item->id,
            'reference_number' => $item->reference_number,
            'title' => $item->title,
            'description' => $item->description,
            'format' => $item->format,
            'format_label' => $item->formatLabel(),
            'priority' => $item->priority,
            'distribution' => $item->distribution,
            'funnel_stage' => $item->funnel_stage,
            'deadline' => $item->deadline?->toDateString(),
            'target_production_date' => $item->target_production_date?->toDateString(),
            'published_at' => $item->published_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
            'is_overdue' => $item->isOverdue(),
            'complexity' => $item->complexity,
            'brand' => $item->brand ? ['id' => $item->brand->id, 'name' => $item->brand->name] : null,
            'campaign' => $item->campaign ? ['id' => $item->campaign->id, 'name' => $item->campaign->name] : null,
            'status' => $item->status ? $item->status->only(['id', 'name', 'slug', 'color']) : null,
            'owner' => $this->person($item->owner),
            'reviewer' => $this->person($item->reviewer),
            'platforms' => $item->relationLoaded('platformVersions')
                ? $item->platformVersions->map(fn (ContentPlatformVersion $version) => [
                    'id' => $version->platform?->id,
                    'name' => $version->platform?->name,
                    'slug' => $version->platform?->slug,
                    'color' => $version->platform?->color,
                    'format' => $version->format,
                    'scheduled_at' => $version->scheduled_at?->toIso8601String(),
                    'publishing_status' => $version->publishing_status,
                ])->values()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(ContentItem $item, User $viewer): array
    {
        $item->loadMissing([
            'brand', 'campaign', 'status', 'pillar', 'objective', 'audienceSegment',
            'creator', 'owner', 'copywriter', 'videoEditor', 'reviewer', 'collaborators',
            'platformVersions.platform', 'platformVersions.performanceReports', 'platformVersions.creativeAsset',
            'assets.uploader', 'comments.user', 'activities.user', 'approvals.reviewer', 'approvals.asset', 'tags',
        ]);

        return array_merge($this->summary($item), [
            'audience_notes' => $item->audience_notes,
            'pillar' => $item->pillar ? ['id' => $item->pillar->id, 'name' => $item->pillar->name] : null,
            'objective' => $item->objective ? ['id' => $item->objective->id, 'name' => $item->objective->name] : null,
            'audience_segment' => $item->audienceSegment ? ['id' => $item->audienceSegment->id, 'name' => $item->audienceSegment->name] : null,
            'creator' => $this->person($item->creator),
            'copywriter' => $this->person($item->copywriter),
            'video_editor' => $this->person($item->videoEditor),
            'collaborators' => $item->collaborators->map(fn (User $user) => $this->person($user))->values(),
            'hypothesis' => $item->hypothesis,
            'success_metric' => $item->success_metric,
            'original_idea' => $item->original_idea,
            'hook' => $item->hook,
            'main_message' => $item->main_message,
            'content_angle' => $item->content_angle,
            'creative_concept' => $item->creative_concept,
            'storyline' => $item->storyline,
            'caption_draft' => $item->caption_draft,
            'call_to_action' => $item->call_to_action,
            'desired_outcome' => $item->desired_outcome,
            'key_talking_points' => $item->key_talking_points,
            'visual_direction' => $item->visual_direction,
            'required_assets' => $item->required_assets,
            'brand_guidelines_notes' => $item->brand_guidelines_notes,
            'production_instructions' => $item->production_instructions,
            'inspiration_source' => $item->inspiration_source,
            'inspiration_url' => self::httpUrl($item->inspiration_url),
            'internal_notes' => $item->internal_notes,
            'scheduled_publish_at' => $item->scheduled_publish_at?->toIso8601String(),
            'created_at' => $item->created_at?->toIso8601String(),
            'archived_at' => $item->archived_at?->toIso8601String(),
            'deleted_at' => $item->deleted_at?->toIso8601String(),
            'versions' => $item->platformVersions->map(fn (ContentPlatformVersion $version) => $this->version($version))->values(),
            'assets' => $item->assets->sortByDesc('id')->map(fn (CreativeAsset $asset) => $this->asset($asset))->values(),
            'comments' => $item->comments->sortBy('created_at')->map(fn ($comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'created_at' => $comment->created_at?->toIso8601String(),
                'user' => $this->person($comment->user),
                'mention_ids' => $comment->mention_ids ?? [],
            ])->values(),
            'activities' => $item->activities->sortByDesc('id')->take(50)->map(fn ($activity) => [
                'id' => $activity->id,
                'action' => $activity->action,
                'description' => $activity->description,
                'properties' => $activity->properties,
                'created_at' => $activity->created_at?->toIso8601String(),
                'user' => $this->person($activity->user),
            ])->values(),
            'approvals' => $item->approvals->sortByDesc('id')->map(fn ($approval) => [
                'id' => $approval->id,
                'decision' => $approval->decision,
                'decision_label' => $approval->decisionLabel(),
                'notes' => $approval->notes,
                'submission_number' => $approval->submission_number,
                'created_at' => $approval->created_at?->toIso8601String(),
                'reviewer' => $this->person($approval->reviewer),
                'asset' => $approval->asset ? ['id' => $approval->asset->id, 'name' => $approval->asset->name, 'version' => $approval->asset->version] : null,
            ])->values(),
            'tags' => $item->tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])->values(),
            'abilities' => [
                'update' => $viewer->can('update', $item),
                'assign' => $viewer->can('assign', $item),
                'approve' => $viewer->can('approve', $item),
                'publish' => $viewer->can('publish', $item),
                'transition' => $viewer->can('transition', $item),
                'delete' => $viewer->can('delete', $item),
                'upload' => $viewer->hasPermission('assets.upload') && $viewer->can('update', $item),
                'comment' => $viewer->hasPermission('comments.create'),
                'performance' => $viewer->hasPermission('performance.manage'),
            ],
        ]);
    }

    /**
     * @param  LengthAwarePaginator<int, ContentItem>  $paginator
     * @return array<string, mixed>
     */
    public function paginate(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => collect($paginator->items())->map(fn (ContentItem $item) => $this->summary($item))->values(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function version(ContentPlatformVersion $version): array
    {
        $reports = $version->relationLoaded('performanceReports') ? $version->performanceReports : $version->performanceReports()->get();
        $latest = $reports->sortByDesc('period_end')->first();

        return [
            'id' => $version->id,
            'format' => $version->format,
            'format_label' => $version->formatLabel(),
            'headline' => $version->headline,
            'caption' => $version->caption,
            'hashtags' => $version->hashtags,
            'call_to_action' => $version->call_to_action,
            'instructions' => $version->instructions,
            'scheduled_at' => $version->scheduled_at?->format('Y-m-d\TH:i'),
            'scheduled_label' => $version->scheduled_at?->toFormattedDateString(),
            'publishing_status' => $version->publishing_status,
            'publishing_status_label' => $version->publishingStatusLabel(),
            'published_url' => self::httpUrl($version->published_url),
            'published_at' => $version->published_at?->toIso8601String(),
            'platform' => $version->platform ? $version->platform->only(['id', 'name', 'slug', 'color']) : null,
            'asset' => $version->creativeAsset ? ['id' => $version->creativeAsset->id, 'name' => $version->creativeAsset->name] : null,
            'metrics' => $latest ? $this->calculator->calculate($latest->toArray()) : null,
            'reports' => $reports->sortByDesc('period_end')->map(fn (PerformanceReport $report) => [
                'id' => $report->id,
                'period_start' => $report->period_start?->toDateString(),
                'period_end' => $report->period_end?->toDateString(),
                'source' => $report->source,
                'reach' => $report->reach,
                'impressions' => $report->impressions,
                'views' => $report->views,
                'video_views_3s' => $report->video_views_3s,
                'watch_time_seconds' => $report->watch_time_seconds,
                'likes' => $report->likes,
                'comments_count' => $report->comments_count,
                'shares' => $report->shares,
                'saves' => $report->saves,
                'link_clicks' => $report->link_clicks,
                'leads' => $report->leads,
                'conversions' => $report->conversions,
                'revenue' => $report->revenue,
                'ad_spend' => $report->ad_spend,
                'notes' => $report->notes,
                'metrics' => $this->calculator->calculate($report->toArray()),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asset(CreativeAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'original_filename' => $asset->original_filename,
            'mime_type' => $asset->mime_type,
            'extension' => $asset->extension,
            'size' => $asset->size,
            'kind' => $asset->kind,
            'kind_label' => $asset->kindLabel(),
            'description' => $asset->description,
            'version' => $asset->version,
            'is_final' => $asset->is_final,
            'previewable' => $asset->isPreviewable(),
            'created_at' => $asset->created_at?->toIso8601String(),
            'uploader' => $this->person($asset->uploader),
            'content_id' => $asset->content_item_id,
            'content_title' => $asset->relationLoaded('contentItem') ? $asset->contentItem?->title : null,
            'brand' => $asset->relationLoaded('brand') && $asset->brand ? ['id' => $asset->brand->id, 'name' => $asset->brand->name] : null,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public function person(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return ['id' => $user->id, 'name' => $user->name];
    }

    public static function httpUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
