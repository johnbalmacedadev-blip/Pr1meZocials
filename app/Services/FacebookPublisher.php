<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Platform;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\FacebookCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FacebookPublisher
{
    public function oauthUrl(string $state): string
    {
        $appId = FacebookCredentials::appId();
        if ($appId === '') {
            throw new RuntimeException('Set your Facebook App ID in Page settings (or FACEBOOK_APP_ID in .env).');
        }

        $scopes = implode(',', config('facebook.scopes', []));

        return 'https://www.facebook.com/'.config('facebook.graph_version', 'v21.0').'/dialog/oauth?'.http_build_query([
            'client_id' => $appId,
            'redirect_uri' => FacebookCredentials::redirectUri(),
            'state' => $state,
            'scope' => $scopes,
            'response_type' => 'code',
        ]);
    }

    /**
     * @return array{access_token: string, expires_in: int|null}
     */
    public function exchangeCodeForUserToken(string $code): array
    {
        $appId = FacebookCredentials::appId();
        $secret = FacebookCredentials::appSecret();

        if ($appId === '' || $secret === '') {
            throw new RuntimeException('Set your Facebook App ID and App Secret in Page settings (or .env).');
        }

        $short = $this->graph()->get('/oauth/access_token', [
            'client_id' => $appId,
            'client_secret' => $secret,
            'redirect_uri' => FacebookCredentials::redirectUri(),
            'code' => $code,
        ]);

        if (! $short->successful()) {
            throw new RuntimeException($this->errorMessage($short->json()));
        }

        $shortToken = (string) data_get($short->json(), 'access_token');
        if ($shortToken === '') {
            throw new RuntimeException('Facebook did not return an access token.');
        }

        $long = $this->graph()->get('/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $secret,
            'fb_exchange_token' => $shortToken,
        ]);

        if ($long->successful() && filled(data_get($long->json(), 'access_token'))) {
            return [
                'access_token' => (string) data_get($long->json(), 'access_token'),
                'expires_in' => data_get($long->json(), 'expires_in') !== null
                    ? (int) data_get($long->json(), 'expires_in')
                    : null,
            ];
        }

        return [
            'access_token' => $shortToken,
            'expires_in' => data_get($short->json(), 'expires_in') !== null
                ? (int) data_get($short->json(), 'expires_in')
                : null,
        ];
    }

    /**
     * Sync every Page the user manages into social_accounts for the given brand.
     *
     * @return list<SocialAccount>
     */
    public function syncManagedPages(User $user, Brand $brand): array
    {
        $token = $user->facebook_user_token;
        if (! filled($token)) {
            throw new RuntimeException('Connect Meta first to load your Facebook Pages.');
        }

        $pages = $this->listManagedPages($token);
        if ($pages === []) {
            throw new RuntimeException('No Facebook Pages were returned for this Meta account.');
        }

        $facebook = Platform::query()->where('slug', 'facebook')->firstOrFail();
        $accounts = [];

        foreach ($pages as $page) {
            $account = SocialAccount::query()->firstOrNew([
                'platform_id' => $facebook->id,
                'external_id' => $page['id'],
            ]);

            $account->fill([
                'brand_id' => $brand->id,
                'name' => $page['name'],
                'handle' => $page['username'] ?? null,
                'access_token' => $page['access_token'],
                'profile_url' => $page['link'] ?? ('https://www.facebook.com/'.$page['id']),
            ]);

            // Keep the user's Active/Inactive choice when refreshing from Meta.
            if (! $account->exists) {
                $account->is_active = true;
            }

            $account->save();
            $accounts[] = $account;
        }

        if (! $user->isAdmin()) {
            $user->socialAccounts()->syncWithoutDetaching(
                collect($accounts)->pluck('id')->map(fn ($id) => (int) $id)->all()
            );
        }

        return $accounts;
    }

    /**
     * List Facebook Pages the token owner can manage.
     *
     * @return list<array{id: string, name: string, access_token: string, username: string|null, link: string|null}>
     */
    public function listManagedPages(string $userAccessToken): array
    {
        $pages = [];
        $nextUrl = null;
        $response = $this->graph()->get('/me/accounts', [
            'fields' => 'id,name,access_token,username,link',
            'limit' => 100,
            'access_token' => $userAccessToken,
        ]);

        while (true) {
            if (! $response->successful()) {
                throw new RuntimeException($this->errorMessage($response->json()));
            }

            foreach (data_get($response->json(), 'data', []) as $page) {
                $id = (string) ($page['id'] ?? '');
                $token = (string) ($page['access_token'] ?? '');
                $name = (string) ($page['name'] ?? '');

                if ($id === '' || $token === '' || $name === '') {
                    continue;
                }

                $pages[] = [
                    'id' => $id,
                    'name' => $name,
                    'access_token' => $token,
                    'username' => isset($page['username']) ? (string) $page['username'] : null,
                    'link' => isset($page['link']) ? (string) $page['link'] : ('https://www.facebook.com/'.$id),
                ];
            }

            $nextUrl = data_get($response->json(), 'paging.next');
            if (! is_string($nextUrl) || $nextUrl === '') {
                break;
            }

            $response = Http::acceptJson()->timeout(120)->get($nextUrl);
        }

        usort($pages, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $pages;
    }

    public function publish(SocialPost $post): SocialPost
    {
        $account = $post->account;
        $this->assertReady($account);

        $scheduleAt = $post->scheduled_publish_at;
        $isScheduled = $scheduleAt !== null;

        if ($isScheduled) {
            $this->assertSchedulable($scheduleAt);
        }

        $post->update(['status' => 'publishing', 'error_message' => null]);

        try {
            if ($post->media_type === 'video') {
                $result = $this->publishVideo($account, $post, $scheduleAt);
            } elseif (count($post->mediaItems()) > 1) {
                $result = $this->publishMultiPhotos($account, $post, $scheduleAt);
            } else {
                $result = $this->publishPhoto($account, $post, $scheduleAt);
            }

            if ($isScheduled) {
                $post->update([
                    'status' => 'scheduled',
                    'facebook_post_id' => $result['id'],
                    'permalink_url' => $result['permalink'] ?? $this->fallbackPermalink($account, $result['id']),
                    'published_at' => null,
                    'scheduled_publish_at' => $scheduleAt,
                    'error_message' => null,
                ]);

                return $post->fresh(['account', 'user', 'contentItem']);
            }

            $post->update([
                'status' => 'published',
                'facebook_post_id' => $result['id'],
                'permalink_url' => $result['permalink'] ?? $this->fallbackPermalink($account, $result['id']),
                'published_at' => now(),
                'scheduled_publish_at' => null,
                'error_message' => null,
            ]);

            $published = $post->fresh(['account']);

            try {
                return $this->syncMetrics($published);
            } catch (\Throwable) {
                // Post already went live on Facebook — keep it published even if insights lag.
                return $published;
            }
        } catch (\Throwable $exception) {
            $post->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function updateCaption(SocialPost $post, string $caption): SocialPost
    {
        $account = $post->account;
        $this->assertReady($account);

        if (! $post->facebook_post_id) {
            throw new RuntimeException('This post is not on Facebook yet.');
        }

        if (! in_array($post->status, ['published', 'scheduled'], true)) {
            throw new RuntimeException('Only published or scheduled posts can be edited.');
        }

        $fields = $post->media_type === 'video'
            ? ['description', 'message']
            : ['message', 'caption'];

        $lastError = 'Facebook rejected the caption update.';
        $updated = false;

        foreach ($fields as $field) {
            $response = $this->graph()->asForm()->post('/'.$post->facebook_post_id, [
                $field => $caption,
                'access_token' => $account->access_token,
            ]);

            if ($response->successful()) {
                $updated = true;
                break;
            }

            $lastError = $this->errorMessage($response->json());
        }

        if (! $updated) {
            throw new RuntimeException($lastError);
        }

        $post->update(['caption' => $caption, 'error_message' => null]);

        return $post->fresh(['account', 'user', 'contentItem']);
    }

    public function syncMetrics(SocialPost $post): SocialPost
    {
        if (! $post->facebook_post_id || ! $post->account?->access_token) {
            return $post;
        }

        $response = $this->graph()->get('/'.$post->facebook_post_id, [
            'fields' => 'permalink_url,shares,likes.summary(true).limit(0),comments.summary(true).limit(0),insights.metric(post_impressions_unique,post_video_views)',
            'access_token' => $post->account->access_token,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json()));
        }

        $payload = $response->json();
        $likes = (int) data_get($payload, 'likes.summary.total_count', 0);
        $comments = (int) data_get($payload, 'comments.summary.total_count', 0);
        $shares = (int) data_get($payload, 'shares.count', 0);
        $reach = 0;
        $views = 0;

        foreach (data_get($payload, 'insights.data', []) as $insight) {
            $name = $insight['name'] ?? '';
            $value = (int) data_get($insight, 'values.0.value', 0);
            if ($name === 'post_impressions_unique') {
                $reach = $value;
            }
            if ($name === 'post_video_views') {
                $views = $value;
            }
        }

        if ($post->media_type === 'video' && $views === 0) {
            $video = $this->graph()->get('/'.$post->facebook_post_id, [
                'fields' => 'views',
                'access_token' => $post->account->access_token,
            ]);
            if ($video->successful()) {
                $views = (int) data_get($video->json(), 'views', 0);
            }
        }

        $engaged = $likes + $comments + $shares;
        $engagementRate = $reach > 0 ? round(($engaged / $reach) * 100, 2) : null;

        $post->update([
            'permalink_url' => data_get($payload, 'permalink_url') ?: $post->permalink_url,
            'likes_count' => $likes,
            'comments_count' => $comments,
            'shares_count' => $shares,
            'views_count' => $views,
            'reach' => $reach,
            'engagement_rate' => $engagementRate,
            'metrics_synced_at' => now(),
        ]);

        return $post->fresh(['account', 'user']);
    }

    /**
     * @return array{id: string, permalink?: string|null}
     */
    private function publishPhoto(SocialAccount $account, SocialPost $post, mixed $scheduleAt = null): array
    {
        $item = $post->mediaItems()[0] ?? null;
        if (! $item) {
            throw new RuntimeException('Media file is missing.');
        }

        $path = $this->absolutePathFor($item['path']);
        $payload = [
            'caption' => (string) $post->caption,
            'access_token' => $account->access_token,
            ...$this->schedulePayload($scheduleAt),
        ];

        $response = $this->graph()
            ->attach('source', file_get_contents($path), $item['name'] ?: 'poster.jpg')
            ->post('/'.$account->external_id.'/photos', $payload);

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json()));
        }

        $photoId = (string) data_get($response->json(), 'id');
        $postId = (string) (data_get($response->json(), 'post_id') ?: $account->external_id.'_'.$photoId);

        return ['id' => $postId, 'permalink' => null];
    }

    /**
     * Upload unpublished photos, then attach them to one feed post (multi-image).
     *
     * @return array{id: string, permalink?: string|null}
     */
    private function publishMultiPhotos(SocialAccount $account, SocialPost $post, mixed $scheduleAt = null): array
    {
        $items = $post->mediaItems();
        if (count($items) < 2) {
            return $this->publishPhoto($account, $post, $scheduleAt);
        }

        $mediaFbids = [];
        foreach ($items as $index => $item) {
            $path = $this->absolutePathFor($item['path']);
            $response = $this->graph()
                ->attach('source', file_get_contents($path), $item['name'] ?: 'poster-'.($index + 1).'.jpg')
                ->post('/'.$account->external_id.'/photos', [
                    'published' => 'false',
                    'temporary' => 'true',
                    'access_token' => $account->access_token,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException($this->errorMessage($response->json()));
            }

            $photoId = (string) data_get($response->json(), 'id');
            if ($photoId === '') {
                throw new RuntimeException('Facebook did not return a photo id for image '.($index + 1).'.');
            }

            $mediaFbids[] = ['media_fbid' => $photoId];
        }

        $payload = [
            'message' => (string) $post->caption,
            'attached_media' => json_encode($mediaFbids),
            'access_token' => $account->access_token,
            ...$this->schedulePayload($scheduleAt),
        ];

        $response = $this->graph()->asForm()->post('/'.$account->external_id.'/feed', $payload);

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json()));
        }

        $id = (string) data_get($response->json(), 'id');
        if ($id === '') {
            throw new RuntimeException('Facebook did not return a post id for the multi-image post.');
        }

        return ['id' => $id, 'permalink' => null];
    }

    /**
     * @return array{id: string, permalink?: string|null}
     */
    private function publishVideo(SocialAccount $account, SocialPost $post, mixed $scheduleAt = null): array
    {
        $item = $post->mediaItems()[0] ?? null;
        if (! $item) {
            throw new RuntimeException('Media file is missing.');
        }

        $path = $this->absolutePathFor($item['path']);
        $size = filesize($path);
        $endpoint = '/'.$account->external_id.'/videos';

        // Resumable upload: the file is streamed from disk in the pieces Facebook asks for,
        // so large videos never have to fit in PHP memory or in a single request.
        $start = $this->videoGraph()->asForm()->post($endpoint, [
            'upload_phase' => 'start',
            'file_size' => $size,
            'access_token' => $account->access_token,
        ]);
        if (! $start->successful()) {
            throw new RuntimeException($this->errorMessage($start->json()));
        }

        $videoId = (string) data_get($start->json(), 'video_id');
        $session = (string) data_get($start->json(), 'upload_session_id');
        $from = (int) data_get($start->json(), 'start_offset', 0);
        $to = (int) data_get($start->json(), 'end_offset', 0);

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Media file is missing.');
        }

        try {
            while ($from < $to) {
                fseek($handle, $from);
                $chunk = (string) fread($handle, $to - $from);

                $transfer = null;
                foreach ([1, 2, 3] as $attempt) {
                    $transfer = $this->videoGraph()
                        ->attach('video_file_chunk', $chunk, 'chunk')
                        ->post($endpoint, [
                            'upload_phase' => 'transfer',
                            'upload_session_id' => $session,
                            'start_offset' => $from,
                            'access_token' => $account->access_token,
                        ]);
                    if ($transfer->successful()) {
                        break;
                    }
                    sleep($attempt);
                }

                if (! $transfer?->successful()) {
                    throw new RuntimeException($this->errorMessage($transfer?->json()));
                }

                $from = (int) data_get($transfer->json(), 'start_offset', $to);
                $to = (int) data_get($transfer->json(), 'end_offset', $from);
            }
        } finally {
            fclose($handle);
        }

        $finish = $this->videoGraph()->asForm()->post($endpoint, [
            'upload_phase' => 'finish',
            'upload_session_id' => $session,
            'access_token' => $account->access_token,
            'description' => (string) $post->caption,
            ...$this->schedulePayload($scheduleAt),
        ]);

        if (! $finish->successful() || data_get($finish->json(), 'success') === false) {
            throw new RuntimeException($this->errorMessage($finish->json()));
        }

        return ['id' => $videoId, 'permalink' => null];
    }

    /**
     * Facebook asks for video uploads to go to its dedicated video host.
     */
    private function videoGraph(): PendingRequest
    {
        $version = config('facebook.graph_version', 'v21.0');

        return Http::baseUrl('https://graph-video.facebook.com/'.$version)
            ->acceptJson()
            ->timeout(300);
    }

    /**
     * @return array{published: string, scheduled_publish_time?: int}
     */
    private function schedulePayload(mixed $scheduleAt): array
    {
        if ($scheduleAt === null) {
            return ['published' => 'true'];
        }

        return [
            'published' => 'false',
            'scheduled_publish_time' => Carbon::parse($scheduleAt)->getTimestamp(),
        ];
    }

    private function assertSchedulable(mixed $scheduleAt): void
    {
        $when = Carbon::parse($scheduleAt);
        $min = now()->addMinutes(10);
        $max = now()->addDays(75);

        if ($when->lt($min)) {
            throw new RuntimeException('Facebook schedules must be at least 10 minutes from now.');
        }

        if ($when->gt($max)) {
            throw new RuntimeException('Facebook schedules must be within 75 days.');
        }
    }

    private function absolutePathFor(string $relativePath): string
    {
        if ($relativePath === '' || ! Storage::disk('local')->exists($relativePath)) {
            throw new RuntimeException('Media file is missing.');
        }

        return Storage::disk('local')->path($relativePath);
    }

    private function assertReady(?SocialAccount $account): void
    {
        if (! $account) {
            throw new RuntimeException('Choose a Facebook page first.');
        }

        if (! filled($account->external_id) || ! filled($account->access_token)) {
            throw new RuntimeException(
                'This Facebook page is missing a Page ID or access token. An admin or manager must Connect with Meta and Refresh pages so tokens load automatically.'
            );
        }
    }

    private function graph(): PendingRequest
    {
        $version = config('facebook.graph_version', 'v21.0');

        return Http::baseUrl('https://graph.facebook.com/'.$version)
            ->acceptJson()
            ->timeout(120);
    }

    private function fallbackPermalink(SocialAccount $account, string $postId): string
    {
        return 'https://www.facebook.com/'.$account->external_id.'/posts/'.str_replace($account->external_id.'_', '', $postId);
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function errorMessage(?array $json): string
    {
        $message = (string) (data_get($json, 'error.message') ?: 'Facebook rejected the request.');
        $code = (int) data_get($json, 'error.code', 0);

        if ($code === 200 || str_contains($message, 'sufficient administrative permission') || str_contains($message, 'does not have permission to post')) {
            return $message.' Make sure your Meta user is a Page admin with content access (and 2FA if the Page requires it), then reconnect or refresh pages with pages_manage_posts.';
        }

        return $message;
    }
}
