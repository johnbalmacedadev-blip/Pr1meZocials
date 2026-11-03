<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * The team's Google Gemini API key. It stays encrypted in private storage, outside
 * git and .env, so a deploy cannot wipe or publish it. It never goes to the browser.
 */
class GeminiCredentials
{
    private const PATH = 'gemini-credentials.json';

    public static function key(): string
    {
        return (string) SafeEncrypted::get(self::stored()['key'] ?? null);
    }

    public static function isConnected(): bool
    {
        return self::key() !== '';
    }

    /**
     * Last characters only, enough to recognise which key is in use.
     */
    public static function hint(): ?string
    {
        $key = self::key();

        return $key === '' ? null : substr($key, -4);
    }

    public static function connectedBy(): ?string
    {
        return self::isConnected() ? (self::stored()['connected_by'] ?? null) : null;
    }

    public static function save(string $key, string $connectedBy): void
    {
        Storage::disk('local')->put(self::PATH, (string) json_encode([
            'key' => SafeEncrypted::set(trim($key)),
            'connected_by' => $connectedBy,
            'updated_at' => date('c'),
        ]));
    }

    public static function forget(): void
    {
        Storage::disk('local')->delete(self::PATH);
    }

    /**
     * @return array<string, mixed>
     */
    private static function stored(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::PATH)) {
            return [];
        }

        $data = json_decode((string) $disk->get(self::PATH), true);

        return is_array($data) ? $data : [];
    }
}
