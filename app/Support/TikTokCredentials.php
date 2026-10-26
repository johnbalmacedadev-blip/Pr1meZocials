<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * TikTok app credentials for shared hosting.
 * Values in .env win; otherwise we read storage/app/tiktok-credentials.json
 * so a git pull that resets committed .env does not wipe a live Client Secret.
 */
class TikTokCredentials
{
    public static function path(): string
    {
        return storage_path('app/tiktok-credentials.json');
    }

    /**
     * @return array{client_key: string, client_secret: string}
     */
    public static function stored(): array
    {
        $path = self::path();
        if (! is_file($path)) {
            return ['client_key' => '', 'client_secret' => ''];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return ['client_key' => '', 'client_secret' => ''];
        }

        return [
            'client_key' => trim((string) ($data['client_key'] ?? '')),
            'client_secret' => trim((string) ($data['client_secret'] ?? '')),
        ];
    }

    public static function clientKey(): string
    {
        $fromEnv = trim((string) config('tiktok.client_key'));

        return $fromEnv !== '' ? $fromEnv : self::stored()['client_key'];
    }

    public static function clientSecret(): string
    {
        $fromEnv = trim((string) config('tiktok.client_secret'));

        return $fromEnv !== '' ? $fromEnv : self::stored()['client_secret'];
    }

    public static function isConfigured(): bool
    {
        return self::clientKey() !== '' && self::clientSecret() !== '';
    }

    public static function redirectUri(): string
    {
        $configured = trim((string) config('tiktok.redirect_uri'));
        if ($configured !== '' && ! str_contains($configured, '${')) {
            return $configured;
        }

        return URL::route('posting.tiktok.callback');
    }

    public static function save(string $clientKey, string $clientSecret): void
    {
        $directory = dirname(self::path());
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $secret = trim($clientSecret);
        if ($secret === '') {
            $secret = self::clientSecret();
        }

        file_put_contents(
            self::path(),
            json_encode([
                'client_key' => trim($clientKey),
                'client_secret' => $secret,
                'updated_at' => date('c'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
            LOCK_EX
        );
    }

    public static function makeOAuthState(int $userId, int $brandId): string
    {
        $body = rtrim(strtr(base64_encode((string) json_encode([
            'uid' => $userId,
            'brand_id' => $brandId,
            'exp' => now()->addMinutes(30)->timestamp,
            'n' => Str::random(24),
        ])), '+/', '-_'), '=');

        return $body.'.'.hash_hmac('sha256', $body, self::signingKey());
    }

    /**
     * @return array{uid: int, brand_id: int}|null
     */
    public static function parseOAuthState(string $state, int $userId): ?array
    {
        $parts = explode('.', $state, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$body, $signature] = $parts;
        $expected = hash_hmac('sha256', $body, self::signingKey());
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $json = base64_decode(strtr($body, '-_', '+/'), true);
        $payload = json_decode($json !== false ? $json : 'null', true);
        if (! is_array($payload)) {
            return null;
        }

        if ((int) ($payload['uid'] ?? 0) !== $userId) {
            return null;
        }

        if ((int) ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        $brandId = (int) ($payload['brand_id'] ?? 0);
        if ($brandId < 1) {
            return null;
        }

        return [
            'uid' => $userId,
            'brand_id' => $brandId,
        ];
    }

    private static function signingKey(): string
    {
        return (string) config('app.key');
    }
}
