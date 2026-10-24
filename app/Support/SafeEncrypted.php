<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class SafeEncrypted
{
    public static function get(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        try {
            return Crypt::decryptString($payload);
        } catch (DecryptException|Throwable) {
            // Plaintext leftovers or APP_KEY mismatch — treat as missing so pages still load.
            return null;
        }
    }

    public static function set(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString($value);
    }

    public static function present(?string $payload): bool
    {
        return $payload !== null && $payload !== '';
    }
}
