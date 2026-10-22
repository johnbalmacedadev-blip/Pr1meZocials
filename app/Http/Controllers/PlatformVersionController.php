<?php

namespace App\Http\Controllers;

use App\Enums\ContentFormat;
use App\Enums\PublishingStatus;
use App\Models\ContentItem;
use App\Models\ContentPlatformVersion;
use App\Rules\HttpUrl;
use App\Services\ActivityLogger;
use App\Services\PublishingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformVersionController extends Controller
{
    public function store(Request $request, ContentItem $content, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('update', $content);

        $data = $this->validated($request, $content);

        $version = DB::transaction(function () use ($content, $data, $request, $activity) {
            $version = $content->platformVersions()->create($data);
            $activity->log($content, $request->user(), 'version_created', 'Added a '.$version->formatLabel().' for '.$version->platform->name.'.', [
                'version_id' => $version->id,
            ]);

            return $version;
        });

        return back()->with('success', 'Platform version added. The original idea stays linked.');
    }

    public function update(Request $request, ContentPlatformVersion $version, PublishingService $publishing, ActivityLogger $activity): RedirectResponse
    {
        $version->load('contentItem');
        $this->authorize('update', $version->contentItem);

        $data = $this->validated($request, $version->contentItem);
        $deadline = $version->contentItem->deadline?->toDateString();
        $schedule = $data['scheduled_at'] ?? null;
        unset($data['scheduled_at']);

        $version->fill($data);
        $version->save();

        if ($request->exists('scheduled_at')) {
            abort_unless($request->user()->can('publish', $version->contentItem), 403);

            if ($schedule) {
                $publishing->reschedule($version, $request->user(), Carbon::parse($schedule));
            } else {
                $version->scheduled_at = null;

                if ($version->publishing_status === 'scheduled') {
                    $version->publishing_status = 'draft';
                }

                $version->save();
            }
        }

        $version->contentItem->refresh();
        abort_if($version->contentItem->deadline?->toDateString() !== $deadline, 500, 'A publishing change altered the production deadline.');

        $activity->log($version->contentItem, $request->user(), 'version_updated', 'Updated the '.$version->platform->name.' version.', [
            'version_id' => $version->id,
        ]);

        return back()->with('success', 'Platform version saved.');
    }

    public function reschedule(Request $request, ContentPlatformVersion $version, PublishingService $publishing): RedirectResponse
    {
        $version->load('contentItem.status');
        $this->authorize('publish', $version->contentItem);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
        ]);

        $publishing->reschedule($version, $request->user(), Carbon::parse($data['scheduled_at']));

        return back()->with('success', 'Publishing date moved. The production deadline was left unchanged.');
    }

    public function publish(Request $request, ContentPlatformVersion $version, PublishingService $publishing): RedirectResponse
    {
        $version->load('contentItem');
        $this->authorize('publish', $version->contentItem);

        $data = $request->validate([
            'published_url' => ['required', 'string', 'max:2048', new HttpUrl],
            'published_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'mark_content_published' => ['nullable', 'boolean'],
        ]);

        $publishing->publish(
            $version,
            $request->user(),
            $data['published_url'],
            isset($data['published_at']) ? Carbon::parse($data['published_at']) : null,
            $data['notes'] ?? null,
            $request->boolean('mark_content_published', true),
        );

        return back()->with('success', 'Publication recorded. Performance can be entered manually until a platform connection exists.');
    }

    public function destroy(Request $request, ContentPlatformVersion $version, ActivityLogger $activity): RedirectResponse
    {
        $version->load('contentItem');
        $this->authorize('update', $version->contentItem);
        abort_unless($request->user()->hasPermission('content.update.any'), 403);

        $name = $version->platform->name;
        $version->delete();
        $activity->log($version->contentItem, $request->user(), 'version_updated', 'Removed the '.$name.' version.');

        return back()->with('success', 'Platform version removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ContentItem $content): array
    {
        $data = $request->validate([
            'platform_id' => ['required', 'integer', 'exists:platforms,id'],
            'social_account_id' => ['nullable', 'integer', Rule::exists('social_accounts', 'id')->where('brand_id', $content->brand_id)],
            'format' => ['required', Rule::enum(ContentFormat::class)],
            'headline' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:10000'],
            'hashtags' => ['nullable', 'string', 'max:500'],
            'call_to_action' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'scheduled_at' => ['nullable', 'date'],
            'publishing_status' => ['nullable', Rule::enum(PublishingStatus::class)],
            'creative_asset_id' => ['nullable', 'integer', Rule::exists('creative_assets', 'id')->where('content_item_id', $content->id)],
        ]);

        $data['publishing_status'] = $data['publishing_status'] ?? PublishingStatus::Draft->value;

        if (($data['publishing_status'] ?? null) === PublishingStatus::Published->value) {
            $data['publishing_status'] = $request->route('version')?->publishing_status ?? PublishingStatus::Draft->value;
        }

        return $data;
    }
}
