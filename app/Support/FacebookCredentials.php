<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Meta app credentials for shared hosting.
 * Values in .env win; otherwise we read storage/app/facebook-credentials.json
 * so a git pull that resets committed .env does not wipe a live App Secret.
 */
class FacebookCredentials
{
    public static function path(): string
    {
        return storage_path('app/facebook-credentials.json');
    }

    /**
     * @return array{app_id: string, app_secret: string}
     */
    public static function stored(): array
    {
        $path = self::path();
        if (! is_file($path)) {
            return ['app_id' => '', 'app_secret' => ''];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return ['app_id' => '', 'app_secret' => ''];
        }

        return [
            'app_id' => trim((string) ($data['app_id'] ?? '')),
            'app_secret' => trim((string) ($data['app_secret'] ?? '')),
        ];
    }

    public static function appId(): string
    {
        $fromEnv = trim((string) config('facebook.app_id'));

        return $fromEnv !== '' ? $fromEnv : self::stored()['app_id'];
    }

    public static function appSecret(): string
    {
        $fromEnv = trim((string) config('facebook.app_secret'));

        return $fromEnv !== '' ? $fromEnv : self::stored()['app_secret'];
    }

    public static function isConfigured(): bool
    {
        return self::appId() !== '' && self::appSecret() !== '';
    }

    public static function redirectUri(): string
    {
        $configured = trim((string) config('facebook.redirect_uri'));
        // Nested ${APP_URL} left unresolved, or empty → use the live route.
        if ($configured !== '' && ! str_contains($configured, '${')) {
            return $configured;
        }

        return URL::route('posting.facebook.callback');
    }

    public static function save(string $appId, string $appSecret): void
    {
        $directory = dirname(self::path());
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $secret = trim($appSecret);
        // Keep the previously saved secret when the form submits an empty field.
        if ($secret === '') {
            $secret = self::appSecret();
        }

        file_put_contents(
            self::path(),
            json_encode([
                'app_id' => trim($appId),
                'app_secret' => $secret,
                'updated_at' => date('c'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
            LOCK_EX
        );
    }

    /**
     * Signed OAuth state — survives lost sessions/cache on shared hosting.
     */
    public static function makeOAuthState(int $userId): string
    {
        $body = rtrim(strtr(base64_encode((string) json_encode([
            'uid' => $userId,
            'exp' => now()->addMinutes(30)->timestamp,
            'n' => Str::random(24),
        ])), '+/', '-_'), '=');

        return $body.'.'.hash_hmac('sha256', $body, self::signingKey());
    }

    public static function oauthStateIsValid(string $state, int $userId): bool
    {
        $parts = explode('.', $state, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return false;
        }

        [$body, $signature] = $parts;
        $expected = hash_hmac('sha256', $body, self::signingKey());
        if (! hash_equals($expected, $signature)) {
            return false;
        }

        $json = base64_decode(strtr($body, '-_', '+/'), true);
        $payload = json_decode($json !== false ? $json : 'null', true);
        if (! is_array($payload)) {
            return false;
        }

        if ((int) ($payload['uid'] ?? 0) !== $userId) {
            return false;
        }

        return (int) ($payload['exp'] ?? 0) >= time();
    }

    private static function signingKey(): string
    {
        return (string) config('app.key');
    }
}
