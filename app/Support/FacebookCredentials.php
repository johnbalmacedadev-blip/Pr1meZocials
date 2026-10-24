<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

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

        file_put_contents(
            self::path(),
            json_encode([
                'app_id' => trim($appId),
                'app_secret' => trim($appSecret),
                'updated_at' => date('c'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
            LOCK_EX
        );
    }
}
