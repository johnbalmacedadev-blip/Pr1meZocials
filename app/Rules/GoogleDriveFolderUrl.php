<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GoogleDriveFolderUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Paste a Google Drive folder link.');

            return;
        }

        $parts = parse_url($value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = (string) ($parts['path'] ?? '');
        $query = (string) ($parts['query'] ?? '');

        $isDrive = in_array($scheme, ['http', 'https'], true) && $host === 'drive.google.com';
        $isFolder = str_contains($path, '/folders/') || str_contains($path, '/folderview') || str_contains($query, 'id=');

        if (! $isDrive || ! $isFolder) {
            $fail('Paste a Google Drive folder link.');
        }
    }
}
