<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\Platform;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Services\FacebookPublisher;
use App\Services\NotificationService;
use App\Support\FacebookCredentials;
use App\Support\PostingScheduleSlots;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SocialPostingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermission('content.publish'), 403);

        try {
            $accessiblePageIds = $user->accessibleSocialAccountIds();

            $pages = SocialAccount::query()
                ->with('brand:id,name')
                ->whereHas('platform', fn ($query) => $query->where('slug', 'facebook'))
                ->when(! $user->isAdmin(), fn ($query) => $query->whereIn('brand_id', $user->accessibleBrandIds() ?: [0]))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (SocialAccount $account) => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'handle' => $account->handle,
                    'external_id' => $account->external_id,
                    'is_active' => $account->is_active,
                    'is_ready' => $account->isFacebookReady(),
                    'can_post' => $user->isAdmin() || in_array((int) $account->id, $accessiblePageIds, true),
                    'brand' => $account->brand?->only(['id', 'name']),
                    'profile_url' => $account->profile_url,
                ]);

            $postsQuery = SocialPost::query()
                ->with(['account:id,name,external_id', 'user:id,name', 'contentItem:id,title,reference_number'])
                ->whereHas('account', function ($query) use ($user, $accessiblePageIds) {
                    $query->whereHas('platform', fn ($platform) => $platform->where('slug', 'facebook'));
                    if (! $user->isAdmin()) {
                        $query->whereIn('brand_id', $user->accessibleBrandIds() ?: [0])
                            ->whereIn('id', $accessiblePageIds ?: [0]);
                    }
                });

            $posts = (clone $postsQuery)
                ->latest('id')
                ->limit(40)
                ->get();

            $focusPostId = $request->integer('post') ?: null;
            if ($focusPostId && ! $posts->contains('id', $focusPostId)) {
                $focused = (clone $postsQuery)->whereKey($focusPostId)->first();
                if ($focused) {
                    $posts = $posts->prepend($focused)->unique('id')->values();
                } else {
                    $focusPostId = null;
                }
            }

            $contentOptions = ContentItem::query()
                ->visibleTo($user)
                ->whereHas('status', fn ($query) => $query->where('slug', '!=', 'archived'))
                ->with('status:id,name')
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'title', 'reference_number', 'status_id'])
                ->map(fn (ContentItem $item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'reference_number' => $item->reference_number,
                    'status' => $item->status?->name,
                ])
                ->values();

            $tab = $request->string('tab')->toString();
            if (! in_array($tab, ['compose', 'history', 'pages'], true)) {
                $tab = $focusPostId ? 'history' : 'compose';
            }

            return Inertia::render('Posting/Index', [
                'pages' => $pages,
                'posts' => $posts->map(fn (SocialPost $post) => $this->presentPost($post))->values(),
                'contentOptions' => $contentOptions,
                'brands' => $user->accessibleBrands()->map->only(['id', 'name'])->values(),
                'metaConnected' => $user->hasFacebookConnection(),
                'metaConfigured' => FacebookCredentials::isConfigured(),
                'metaAppId' => FacebookCredentials::appId(),
                'metaAppSecret' => FacebookCredentials::appSecret(),
                'metaRedirectUri' => FacebookCredentials::redirectUri(),
                'canManagePages' => $user->hasPermission('content.publish'),
                'composeDefaults' => $this->composeDefaults($request),
                'focus' => [
                    'tab' => $tab,
                    'post_id' => $focusPostId,
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            @file_put_contents(
                storage_path('logs/posting-error.log'),
                '['.date('c').'] '.$exception->getMessage()."\n".$exception->getFile().':'.$exception->getLine()."\n\n",
                FILE_APPEND
            );

            return Inertia::render('Posting/Index', [
                'pages' => [],
                'posts' => [],
                'contentOptions' => [],
                'brands' => $user->accessibleBrands()->map->only(['id', 'name'])->values(),
                'metaConnected' => false,
                'metaConfigured' => FacebookCredentials::isConfigured(),
                'metaAppId' => FacebookCredentials::appId(),
                'metaAppSecret' => FacebookCredentials::appSecret(),
                'metaRedirectUri' => FacebookCredentials::redirectUri(),
                'canManagePages' => $user->hasPermission('content.publish'),
                'composeDefaults' => $this->composeDefaults($request),
                'focus' => ['tab' => 'compose', 'post_id' => null],
                'loadError' => $exception->getMessage(),
            ]);
        }
    }

    public function saveMetaCredentials(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('content.publish'), 403);

        $data = $request->validate([
            'app_id' => ['required', 'string', 'max:64'],
            'app_secret' => [
                FacebookCredentials::appSecret() !== '' ? 'nullable' : 'required',
                'string',
                'max:255',
            ],
        ]);

        FacebookCredentials::save($data['app_id'], (string) ($data['app_secret'] ?? ''));

        if (! FacebookCredentials::isConfigured()) {
            return redirect()
                ->route('posting.index', ['tab' => 'pages'])
                ->with('error', 'App Secret is required. Paste it from Meta Developer → Settings → Basic.');
        }

        return redirect()
            ->route('posting.index', ['tab' => 'pages'])
            ->with('success', 'Meta App ID and Secret saved. Click Connect with Meta next — these survive git pulls.');
    }

    public function redirectToFacebook(Request $request, FacebookPublisher $publisher): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('content.publish'), 403);

        if (! FacebookCredentials::isConfigured()) {
            return redirect()
                ->route('posting.index', ['tab' => 'pages'])
                ->with('error', 'Save your Facebook App ID and App Secret in Page settings first.');
        }

        $state = FacebookCredentials::makeOAuthState((int) $request->user()->id);
        // Keep legacy backups; signed state is what actually survives shared hosting.
        $request->session()->put('facebook_oauth_state', $state);
        Cache::put('facebook_oauth_state:'.$state, (int) $request->user()->id, now()->addMinutes(30));

        try {
            return redirect()->away($publisher->oauthUrl($state));
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'meta' => $exception->getMessage(),
            ]);
        }
    }

    public function handleFacebookCallback(Request $request, FacebookPublisher $publisher): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('content.publish'), 403);

        if ($request->filled('error')) {
            return redirect()
                ->route('posting.index', ['tab' => 'pages'])
                ->with('error', (string) $request->input('error_description', 'Meta authorization was cancelled.'));
        }

        $state = (string) $request->input('state');
        $userId = (int) $request->user()->id;
        $expected = (string) $request->session()->pull('facebook_oauth_state');
        $cachedUserId = $state !== '' ? Cache::pull('facebook_oauth_state:'.$state) : null;
        $stateOk = $state !== '' && (
            FacebookCredentials::oauthStateIsValid($state, $userId)
            || ($expected !== '' && hash_equals($expected, $state))
            || ((int) $cachedUserId === $userId)
        );

        if (! $stateOk) {
            return redirect()
                ->route('posting.index', ['tab' => 'pages'])
                ->with('error', 'Invalid Meta login state. Try Connect with Meta again.');
        }

        $code = (string) $request->input('code');
        if ($code === '') {
            return redirect()
                ->route('posting.index', ['tab' => 'pages'])
                ->with('error', 'Meta did not return an authorization code.');
        }

        try {
            $token = $publisher->exchangeCodeForUserToken($code);
            $user = $request->user();
            $user->forceFill([
                'facebook_user_token' => $token['access_token'],
                'facebook_token_expires_at' => $token['expires_in']
                    ? now()->addSeconds($token['expires_in'])
                    : null,
            ])->save();

            $brand = $user->accessibleBrands()->first();
            if (! $brand) {
                return redirect()
                    ->route('posting.index', ['tab' => 'pages'])
                    ->with('error', 'Create a brand first, then refresh pages from Meta.');
            }

            $accounts = $publisher->syncManagedPages($user->fresh(), $brand);
        } catch (\Throwable $exception) {
            return redirect()
                ->route('posting.index', ['tab' => 'pages'])
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('posting.index', ['tab' => 'pages'])
            ->with('success', count($accounts).' Facebook '.str('page')->plural(count($accounts)).' loaded. Turn pages Active to post.');
    }

    public function refreshPages(Request $request, FacebookPublisher $publisher): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('content.publish'), 403);

        $data = $request->validate([
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
        ]);

        $user = $request->user();
        abort_unless($user->hasFacebookConnection(), 422, 'Connect Meta first.');

        $brandId = isset($data['brand_id']) ? (int) $data['brand_id'] : null;
        $brand = $brandId
            ? $user->accessibleBrands()->firstWhere('id', $brandId)
            : $user->accessibleBrands()->first();

        abort_unless($brand, 422, 'Choose a brand for your Facebook Pages.');
        abort_unless($user->canAccessBrand($brand->id), 403);

        try {
            $accounts = $publisher->syncManagedPages($user, $brand);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'meta' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', count($accounts).' Facebook '.str('page')->plural(count($accounts)).' updated.');
    }

    public function destroyMeta(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('content.publish'), 403);

        $request->user()->forceFill([
            'facebook_user_token' => null,
            'facebook_token_expires_at' => null,
        ])->save();

        return back()->with('success', 'Meta account disconnected. Page tokens already saved still work until you refresh.');
    }

    public function updatePage(Request $request, SocialAccount $account): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('content.publish'), 403);

        $account->load('platform');
        abort_unless($account->platform?->slug === 'facebook', 404);
        abort_unless($request->user()->canAccessBrand($account->brand_id), 403);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $account->update(['is_active' => $data['is_active']]);

        $label = $data['is_active'] ? 'active' : 'inactive';

        return back()->with('success', $account->name.' is now '.$label.' for posting.');
    }

    public function store(Request $request, FacebookPublisher $publisher, NotificationService $notifications): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('content.publish'), 403);

        $maxKb = (int) config('ops.upload_max_kb', env('UPLOAD_MAX_KB', 512000));
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        $postMax = $this->iniBytes((string) ini_get('post_max_size'));

        if ($contentLength > 0 && empty($request->all()) && empty($request->allFiles()) && $contentLength > $postMax) {
            throw ValidationException::withMessages([
                'media' => 'File is too large for the server upload limit ('.ini_get('post_max_size').'). Use a smaller poster or raise post_max_size / upload_max_filesize in php.ini, then restart PHP.',
            ]);
        }

        if (! $request->filled('return_to')) {
            $request->merge(['return_to' => null]);
        }

        $data = $request->validate([
            'draft_id' => ['nullable', 'integer', 'exists:social_posts,id'],
            'social_account_id' => ['required', 'integer', 'exists:social_accounts,id'],
            'caption' => ['required', 'string', 'max:5000'],
            'media_type' => ['required', Rule::in(['image', 'video'])],
            'content_item_id' => ['nullable', 'integer', 'exists:content_items,id'],
            'publish_mode' => ['required', Rule::in(['now', 'schedule'])],
            'scheduled_publish_at' => [
                Rule::requiredIf($request->input('publish_mode') === 'schedule'),
                'nullable',
                'date',
                'after:'.now()->addMinutes(9)->toDateTimeString(),
                'before:'.now()->addDays(75)->toDateTimeString(),
            ],
            'media' => ['nullable', 'array', 'max:10'],
            'media.*' => [
                'required',
                'file',
                'max:'.$maxKb,
                Rule::when($request->input('media_type') === 'image', ['mimes:jpg,jpeg,png,webp,gif']),
                Rule::when($request->input('media_type') === 'video', ['mimes:mp4,mov,m4v']),
            ],
            'return_to' => ['nullable', 'string', 'max:64', 'regex:/^\\/posting-schedule(\\?week=\\d{4}-\\d{2}-\\d{2})?$/'],
        ]);

        /** @var list<\Illuminate\Http\UploadedFile> $files */
        $files = array_values(array_filter(
            is_array($request->file('media')) ? $request->file('media') : [$request->file('media')],
        ));

        $draft = null;
        if (! empty($data['draft_id'])) {
            $draft = SocialPost::query()->findOrFail((int) $data['draft_id']);
            abort_unless($draft->status === 'draft', 422, 'Only draft posts can be resumed from Compose.');
            abort_unless((int) $draft->user_id === (int) $request->user()->id || $request->user()->isAdmin(), 403);
            $draft->load('account.platform');
            abort_unless($draft->account?->platform?->slug === 'facebook', 404);
            abort_unless($request->user()->canAccessBrand($draft->account->brand_id), 403);
            abort_unless($request->user()->canAccessSocialAccount($draft->account->id), 403);
        }

        if ($files === [] && $draft === null) {
            throw ValidationException::withMessages([
                'media' => 'Attach at least one image or video.',
            ]);
        }

        if ($files !== []) {
            if ($data['media_type'] === 'video' && count($files) !== 1) {
                throw ValidationException::withMessages([
                    'media' => 'Only one video can be posted at a time.',
                ]);
            }

            if ($data['media_type'] === 'image' && count($files) > 10) {
                throw ValidationException::withMessages([
                    'media' => 'You can attach up to 10 images in one post.',
                ]);
            }
        }

        $account = SocialAccount::query()->with('platform')->findOrFail($data['social_account_id']);
        abort_unless($account->platform?->slug === 'facebook', 422);
        abort_unless($request->user()->canAccessBrand($account->brand_id), 403);
        abort_unless($request->user()->canAccessSocialAccount($account->id), 403, 'You are not allowed to post to this Facebook page.');
        abort_unless($account->isFacebookReady(), 422, 'Choose a ready Facebook page first.');

        $contentItemId = isset($data['content_item_id']) ? (int) $data['content_item_id'] : null;
        if ($contentItemId) {
            $contentItem = ContentItem::query()->visibleTo($request->user())->find($contentItemId);
            abort_unless($contentItem, 403, 'You cannot link that creative content.');
        }

        $scheduleAt = $data['publish_mode'] === 'schedule'
            ? Carbon::parse($data['scheduled_publish_at'])
            : null;

        if ($files !== []) {
            if ($draft) {
                $oldPaths = array_column($draft->mediaItems(), 'path');
                if ($oldPaths !== []) {
                    Storage::disk('local')->delete($oldPaths);
                }
            }

            $mediaFiles = [];
            foreach ($files as $file) {
                $mediaFiles[] = [
                    'path' => $file->store('social-posts/'.$request->user()->id, 'local'),
                    'name' => $file->getClientOriginalName(),
                ];
            }
        } else {
            $mediaFiles = $draft?->mediaItems() ?? [];
            if ($mediaFiles === []) {
                throw ValidationException::withMessages([
                    'media' => 'This draft has no media left. Attach at least one image or video.',
                ]);
            }
        }

        $first = $mediaFiles[0];
        $mediaName = count($mediaFiles) === 1
            ? $first['name']
            : $first['name'].' + '.(count($mediaFiles) - 1).' more';

        $attributes = [
            'social_account_id' => $account->id,
            'user_id' => $request->user()->id,
            'content_item_id' => $contentItemId,
            'caption' => $data['caption'],
            'media_type' => $data['media_type'],
            'media_path' => $first['path'],
            'media_name' => $mediaName,
            'media_files' => $mediaFiles,
            'status' => 'draft',
            'scheduled_publish_at' => $scheduleAt,
            'error_message' => null,
        ];

        if ($draft) {
            $draft->fill($attributes)->save();
            $post = $draft->fresh();
        } else {
            $post = SocialPost::query()->create($attributes);
        }

        try {
            $published = $publisher->publish($post);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'media' => $exception->getMessage(),
            ]);
        }

        $pageName = $account->name;
        $scheduled = $published->status === 'scheduled';
        $message = $scheduled
            ? 'Scheduled on Facebook for '.$pageName.' at '.$published->scheduled_publish_at?->timezone(config('app.timezone'))->format('M j, Y g:i A').'. Facebook will publish it.'
            : 'Posted to '.$pageName.' on Facebook.';

        $notifications->send(
            $request->user(),
            $scheduled ? 'facebook_post_scheduled' : 'facebook_post_published',
            $scheduled ? 'Scheduled on Facebook' : 'Posted to Facebook',
            $message.($published->permalink_url ? ' Open it from History.' : ''),
            'facebook-post-'.$published->id,
            [
                'social_post_id' => $published->id,
                'social_account_id' => $account->id,
                'permalink_url' => $published->permalink_url,
                'scheduled_publish_at' => $published->scheduled_publish_at?->toIso8601String(),
            ],
        );

        $returnTo = $data['return_to'] ?? null;
        if (is_string($returnTo) && str_starts_with($returnTo, '/posting-schedule')) {
            return redirect($returnTo)->with('success', $message);
        }

        return back()->with('success', $message);
    }

    public function sync(Request $request, SocialPost $post, FacebookPublisher $publisher): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('content.publish'), 403);
        $post->load('account.platform');
        abort_unless($post->account?->platform?->slug === 'facebook', 404);
        abort_unless($request->user()->canAccessBrand($post->account->brand_id), 403);
        abort_unless($request->user()->canAccessSocialAccount($post->account->id), 403);

        try {
            $publisher->syncMetrics($post);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'post' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Engagement updated.');
    }

    public function update(Request $request, SocialPost $post, FacebookPublisher $publisher): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('content.publish'), 403);
        $post->load('account.platform');
        abort_unless($post->account?->platform?->slug === 'facebook', 404);
        abort_unless($request->user()->canAccessBrand($post->account->brand_id), 403);
        abort_unless($request->user()->canAccessSocialAccount($post->account->id), 403);
        abort_unless(
            $post->status === 'scheduled',
            422,
            'Published posts are view-only. Only scheduled posts can have their caption updated.'
        );

        $data = $request->validate([
            'caption' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $publisher->updateCaption($post, $data['caption']);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'caption' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Post caption updated on Facebook.');
    }

    public function destroy(Request $request, SocialPost $post): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('content.publish'), 403);
        $post->load('account.platform');
        abort_unless($post->account?->platform?->slug === 'facebook', 404);
        abort_unless($request->user()->canAccessBrand($post->account->brand_id), 403);
        abort_unless($request->user()->canAccessSocialAccount($post->account->id), 403);
        abort_unless(
            in_array($post->status, ['failed', 'draft'], true),
            422,
            'Only draft or failed posts can be deleted from history.'
        );

        $wasDraft = $post->status === 'draft';
        $paths = array_column($post->mediaItems(), 'path');
        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }

        $post->delete();

        return back()->with('success', $wasDraft ? 'Draft removed.' : 'Failed post removed from history.');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPost(SocialPost $post): array
    {
        return [
            'id' => $post->id,
            'caption' => $post->caption,
            'media_type' => $post->media_type,
            'media_name' => $post->media_name,
            'media_count' => count($post->mediaItems()),
            'status' => $post->status,
            'error_message' => $post->error_message,
            'facebook_post_id' => $post->facebook_post_id,
            'permalink_url' => $post->permalink_url,
            'likes_count' => $post->likes_count,
            'comments_count' => $post->comments_count,
            'shares_count' => $post->shares_count,
            'views_count' => $post->views_count,
            'reach' => $post->reach,
            'engagement_rate' => $post->engagement_rate,
            'metrics_synced_at' => $post->metrics_synced_at?->toIso8601String(),
            'published_at' => $post->published_at?->toIso8601String(),
            'scheduled_publish_at' => $post->scheduled_publish_at?->toIso8601String(),
            'page' => $post->account?->only(['id', 'name', 'external_id']),
            'author' => $post->user?->only(['id', 'name']),
            'content' => $post->contentItem?->only(['id', 'title', 'reference_number']),
        ];
    }

    /**
     * Prefill Compose when arriving from the Facebook Content Schedule grid.
     *
     * @return array{
     *     publish_mode: 'now'|'schedule',
     *     scheduled_publish_at: string|null,
     *     media_type: 'image'|'video',
     *     caption: string,
     *     slot: string|null,
     *     weekday: string|null,
     *     slot_label: string|null,
     *     return_to: string|null
     * }
     */
    private function composeDefaults(Request $request): array
    {
        $slot = $request->string('slot')->toString();
        $weekday = $request->string('weekday')->toString();
        $format = $request->string('format')->toString();
        $title = trim($request->string('title')->toString());
        $date = $request->string('date')->toString();
        $time = $request->string('time')->toString();
        $week = $request->string('week')->toString();
        $returnTo = null;
        if ($request->string('return')->toString() === 'schedule') {
            $returnTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) === 1
                ? '/posting-schedule?week='.$week
                : '/posting-schedule';
        }

        if ($time === '' && in_array($slot, PostingScheduleSlots::KEYS, true)) {
            $time = PostingScheduleSlots::defaultTime($slot);
        }

        $scheduledAt = null;
        if ($date !== '' && $time !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && preg_match('/^\d{2}:\d{2}$/', $time)) {
            try {
                $when = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time);
                $min = now()->addMinutes(10);
                if ($when->lt($min)) {
                    $when = $when->addWeek();
                    while ($when->lt($min)) {
                        $when = $when->addWeek();
                    }
                }
                $max = now()->addDays(75);
                if ($when->lte($max)) {
                    $scheduledAt = $when->format('Y-m-d\TH:i');
                }
            } catch (\Throwable) {
                $scheduledAt = null;
            }
        }

        $slotLabel = null;
        if ($weekday !== '' && $slot !== '') {
            $slotLabel = ucfirst($weekday).' · '.($slot === 'flex' ? 'Flex slot' : ucfirst($slot));
            if ($scheduledAt) {
                $slotLabel .= ' · '.Carbon::createFromFormat('Y-m-d\TH:i', $scheduledAt)->format('M j, g:i A');
            }
        }

        return [
            'publish_mode' => $scheduledAt ? 'schedule' : 'now',
            'scheduled_publish_at' => $scheduledAt,
            'media_type' => $format === 'reel' ? 'video' : 'image',
            'caption' => $title,
            'slot' => $slot !== '' ? $slot : null,
            'weekday' => $weekday !== '' ? $weekday : null,
            'slot_label' => $slotLabel,
            'return_to' => $returnTo,
        ];
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
