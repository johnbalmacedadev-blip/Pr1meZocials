<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Platform;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Support\TikTokCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TikTokPublisher
{
    public function oauthUrl(string $state): string
    {
        if (! TikTokCredentials::isConfigured()) {
            throw new RuntimeException('Save your TikTok Client Key and Client Secret first.');
        }

        $scopes = implode(',', config('tiktok.scopes', ['user.info.basic', 'video.publish']));

        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
            'client_key' => TikTokCredentials::clientKey(),
            'response_type' => 'code',
            'scope' => $scopes,
            'redirect_uri' => TikTokCredentials::redirectUri(),
            'state' => $state,
        ]);
    }

    /**
     * @return array{
     *     access_token: string,
     *     expires_in: int,
     *     refresh_token: string,
     *     refresh_expires_in: int,
     *     open_id: string,
     *     scope: string
     * }
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->post('https://open.tiktokapis.com/v2/oauth/token/', [
                'client_key' => TikTokCredentials::clientKey(),
                'client_secret' => TikTokCredentials::clientSecret(),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => TikTokCredentials::redirectUri(),
            ]);

        $payload = $response->json() ?? [];
        if (! $response->successful() || empty($payload['access_token']) || empty($payload['open_id'])) {
            $message = (string) ($payload['error_description'] ?? $payload['error'] ?? 'TikTok authorization failed.');
            throw new RuntimeException($message);
        }

        return [
            'access_token' => (string) $payload['access_token'],
            'expires_in' => (int) ($payload['expires_in'] ?? 86400),
            'refresh_token' => (string) ($payload['refresh_token'] ?? ''),
            'refresh_expires_in' => (int) ($payload['refresh_expires_in'] ?? 0),
            'open_id' => (string) $payload['open_id'],
            'scope' => (string) ($payload['scope'] ?? ''),
        ];
    }

    /**
     * @return array{display_name: string, avatar_url: string|null}
     */
    public function fetchUserProfile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,union_id,avatar_url,display_name',
            ]);

        $payload = $response->json() ?? [];
        $user = $payload['data']['user'] ?? null;
        if (! $response->successful() || ! is_array($user)) {
            $message = (string) ($payload['error']['message'] ?? 'Could not load TikTok profile.');
            throw new RuntimeException($message !== '' ? $message : 'Could not load TikTok profile.');
        }

        return [
            'display_name' => trim((string) ($user['display_name'] ?? 'TikTok account')) ?: 'TikTok account',
            'avatar_url' => isset($user['avatar_url']) ? (string) $user['avatar_url'] : null,
        ];
    }

    public function connectAccount(Brand $brand, array $token): SocialAccount
    {
        $profile = $this->fetchUserProfile($token['access_token']);
        $platform = Platform::query()->where('slug', 'tiktok')->firstOrFail();

        $account = SocialAccount::query()->firstOrNew([
            'platform_id' => $platform->id,
            'external_id' => $token['open_id'],
        ]);

        $meta = is_array($account->meta) ? $account->meta : [];
        $meta['refresh_token'] = $token['refresh_token'];
        $meta['refresh_expires_at'] = ! empty($token['refresh_expires_in'])
            ? now()->addSeconds((int) $token['refresh_expires_in'])->toIso8601String()
            : null;
        $meta['scope'] = $token['scope'];
        $meta['avatar_url'] = $profile['avatar_url'];

        $account->fill([
            'brand_id' => $brand->id,
            'name' => $profile['display_name'],
            'handle' => null,
            'access_token' => $token['access_token'],
            'token_expires_at' => now()->addSeconds(max(60, (int) $token['expires_in'])),
            'meta' => $meta,
            'profile_url' => 'https://www.tiktok.com/@'.rawurlencode($profile['display_name']),
            'is_active' => true,
        ])->save();

        return $account->fresh();
    }

    public function ensureFreshToken(SocialAccount $account): string
    {
        $token = (string) ($account->access_token ?? '');
        if ($token === '') {
            throw new RuntimeException('This TikTok account has no access token. Reconnect it in TikTok settings.');
        }

        $expiresAt = $account->token_expires_at;
        if ($expiresAt === null || $expiresAt->gt(now()->addMinutes(5))) {
            return $token;
        }

        $meta = is_array($account->meta) ? $account->meta : [];
        $refreshToken = trim((string) ($meta['refresh_token'] ?? ''));
        if ($refreshToken === '') {
            throw new RuntimeException('TikTok token expired. Reconnect the account in TikTok settings.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post('https://open.tiktokapis.com/v2/oauth/token/', [
                'client_key' => TikTokCredentials::clientKey(),
                'client_secret' => TikTokCredentials::clientSecret(),
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        $payload = $response->json() ?? [];
        if (! $response->successful() || empty($payload['access_token'])) {
            $message = (string) ($payload['error_description'] ?? $payload['error'] ?? 'Could not refresh TikTok token.');
            throw new RuntimeException($message);
        }

        $meta['refresh_token'] = (string) ($payload['refresh_token'] ?? $refreshToken);
        if (! empty($payload['refresh_expires_in'])) {
            $meta['refresh_expires_at'] = now()->addSeconds((int) $payload['refresh_expires_in'])->toIso8601String();
        }
        if (! empty($payload['scope'])) {
            $meta['scope'] = (string) $payload['scope'];
        }

        $account->update([
            'access_token' => (string) $payload['access_token'],
            'token_expires_at' => now()->addSeconds(max(60, (int) ($payload['expires_in'] ?? 86400))),
            'meta' => $meta,
        ]);

        return (string) $payload['access_token'];
    }

    /**
     * @return list<string>
     */
    public function privacyOptions(SocialAccount $account): array
    {
        $accessToken = $this->ensureFreshToken($account);
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post('https://open.tiktokapis.com/v2/post/publish/creator_info/query/');

        $payload = $response->json() ?? [];
        $options = $payload['data']['privacy_level_options'] ?? null;
        if (! $response->successful() || ! is_array($options) || $options === []) {
            // Unaudited apps are limited to private posts.
            return ['SELF_ONLY'];
        }

        return array_values(array_filter(array_map('strval', $options)));
    }

    public function publish(SocialPost $post): SocialPost
    {
        $post->loadMissing('account.platform');
        $account = $post->account;
        $this->assertReady($account);

        if ($post->media_type !== 'video') {
            throw new RuntimeException('TikTok cross-post only supports video.');
        }

        $items = $post->mediaItems();
        if ($items === []) {
            throw new RuntimeException('No video file found for this TikTok post.');
        }

        $absolute = Storage::disk('local')->path($items[0]['path']);
        if (! is_file($absolute)) {
            throw new RuntimeException('Video file is missing on the server.');
        }

        $videoSize = filesize($absolute);
        if ($videoSize === false || $videoSize < 1) {
            throw new RuntimeException('Video file is empty or unreadable.');
        }

        $accessToken = $this->ensureFreshToken($account);
        $privacyOptions = $this->privacyOptions($account);
        $privacy = in_array('SELF_ONLY', $privacyOptions, true)
            ? 'SELF_ONLY'
            : ($privacyOptions[0] ?? 'SELF_ONLY');

        $chunkSize = min((int) $videoSize, 10 * 1024 * 1024);
        $totalChunks = (int) max(1, (int) ceil($videoSize / $chunkSize));

        $init = Http::withToken($accessToken)
            ->acceptJson()
            ->post('https://open.tiktokapis.com/v2/post/publish/video/init/', [
                'post_info' => [
                    'title' => mb_substr((string) $post->caption, 0, 2200),
                    'privacy_level' => $privacy,
                    'disable_duet' => false,
                    'disable_comment' => false,
                    'disable_stitch' => false,
                    'brand_content_toggle' => false,
                    'brand_organic_toggle' => false,
                ],
                'source_info' => [
                    'source' => 'FILE_UPLOAD',
                    'video_size' => $videoSize,
                    'chunk_size' => $chunkSize,
                    'total_chunk_count' => $totalChunks,
                ],
            ]);

        $initPayload = $init->json() ?? [];
        $errorCode = (string) ($initPayload['error']['code'] ?? '');
        if (! $init->successful() || $errorCode !== 'ok') {
            $message = (string) ($initPayload['error']['message'] ?? 'TikTok rejected the publish request.');
            $post->update([
                'status' => 'failed',
                'error_message' => $message !== '' ? $message : 'TikTok rejected the publish request.',
            ]);
            throw new RuntimeException($post->error_message);
        }

        $publishId = (string) ($initPayload['data']['publish_id'] ?? '');
        $uploadUrl = (string) ($initPayload['data']['upload_url'] ?? '');
        if ($publishId === '' || $uploadUrl === '') {
            $post->update([
                'status' => 'failed',
                'error_message' => 'TikTok did not return an upload URL.',
            ]);
            throw new RuntimeException('TikTok did not return an upload URL.');
        }

        $mime = str_ends_with(strtolower($items[0]['name']), '.mov')
            ? 'video/quicktime'
            : 'video/mp4';

        $handle = fopen($absolute, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not open the video for upload.');
        }

        try {
            $offset = 0;
            while ($offset < $videoSize) {
                $length = min($chunkSize, $videoSize - $offset);
                $chunk = fread($handle, $length);
                if ($chunk === false || strlen($chunk) !== $length) {
                    throw new RuntimeException('Failed reading video chunk for TikTok upload.');
                }

                $end = $offset + $length - 1;
                $upload = Http::withBody($chunk, $mime)
                    ->withHeaders([
                        'Content-Length' => (string) $length,
                        'Content-Range' => "bytes {$offset}-{$end}/{$videoSize}",
                    ])
                    ->put($uploadUrl);

                if (! $upload->successful()) {
                    $post->update([
                        'status' => 'failed',
                        'error_message' => 'TikTok video upload failed (HTTP '.$upload->status().').',
                        'tiktok_publish_id' => $publishId,
                    ]);
                    throw new RuntimeException($post->error_message);
                }

                $offset += $length;
            }
        } finally {
            fclose($handle);
        }

        $post->update([
            'status' => 'published',
            'tiktok_publish_id' => $publishId,
            'permalink_url' => $account->profile_url,
            'published_at' => now(),
            'error_message' => null,
        ]);

        return $post->fresh();
    }

    private function assertReady(?SocialAccount $account): void
    {
        if (! $account || $account->platform?->slug !== 'tiktok') {
            throw new RuntimeException('Choose a TikTok account first.');
        }

        if (! $account->isTikTokReady()) {
            throw new RuntimeException('Connect a TikTok account in TikTok settings first.');
        }
    }
}
