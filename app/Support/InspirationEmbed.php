<?php

namespace App\Support;

class InspirationEmbed
{
    /**
     * @return array{embed_url: string|null, kind: 'iframe'|'video'|null}
     */
    public static function resolve(?string $platformSlug, string $url): array
    {
        $embed = self::forSlug($platformSlug, $url);

        if ($embed === null) {
            $embed = self::forSlug(self::slugFromUrl($url), $url);
        }

        if ($embed === null) {
            return ['embed_url' => null, 'kind' => null];
        }

        $kind = preg_match('/\.(mp4|webm|ogg)(\?|#|$)/i', $embed) === 1 ? 'video' : 'iframe';

        return ['embed_url' => $embed, 'kind' => $kind];
    }

    private static function forSlug(?string $slug, string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';

        return match ($slug) {
            'youtube' => self::youtube($url, $path, $query),
            'instagram' => self::instagram($path),
            'tiktok' => self::tiktok($path),
            'facebook' => self::facebook($url),
            'x' => self::x($path),
            'pinterest' => self::pinterest($path),
            default => self::directVideo($url),
        };
    }

    private static function slugFromUrl(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        return match (true) {
            str_contains($host, 'youtube.com') || $host === 'youtu.be' => 'youtube',
            str_contains($host, 'instagram.com') || $host === 'instagr.am' => 'instagram',
            str_contains($host, 'tiktok.com') => 'tiktok',
            str_contains($host, 'facebook.com') || $host === 'fb.watch' => 'facebook',
            $host === 'x.com' || str_contains($host, 'twitter.com') => 'x',
            str_contains($host, 'pinterest.') => 'pinterest',
            default => null,
        };
    }

    private static function youtube(string $url, string $path, string $query): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $id = null;

        if (str_contains($host, 'youtu.be')) {
            $id = trim($path, '/');
        }

        parse_str($query, $params);
        if (isset($params['v']) && is_string($params['v'])) {
            $id = $params['v'];
        }

        if (preg_match('#/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{6,})#', $path, $matches) === 1) {
            $id = $matches[1];
        }

        if (! is_string($id) || preg_match('/^[A-Za-z0-9_-]{6,}$/', $id) !== 1) {
            return null;
        }

        return 'https://www.youtube.com/embed/'.$id;
    }

    private static function instagram(string $path): ?string
    {
        if (preg_match('#/(reel|reels|p|tv)/([A-Za-z0-9_-]+)#', $path, $matches) !== 1) {
            return null;
        }

        $type = $matches[1] === 'reels' ? 'reel' : $matches[1];

        return 'https://www.instagram.com/'.$type.'/'.$matches[2].'/embed';
    }

    private static function tiktok(string $path): ?string
    {
        if (preg_match('#/video/(\d+)#', $path, $matches) !== 1) {
            return null;
        }

        return 'https://www.tiktok.com/embed/v2/'.$matches[1];
    }

    private static function facebook(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (! str_contains($host, 'facebook.com') && $host !== 'fb.watch') {
            return null;
        }

        return 'https://www.facebook.com/plugins/video.php?href='.rawurlencode($url).'&show_text=false&width=400';
    }

    private static function x(string $path): ?string
    {
        if (preg_match('#/status/(\d+)#', $path, $matches) !== 1) {
            return null;
        }

        return 'https://platform.twitter.com/embed/Tweet.html?id='.$matches[1];
    }

    private static function pinterest(string $path): ?string
    {
        if (preg_match('#/pin/(\d+)#', $path, $matches) !== 1) {
            return null;
        }

        return 'https://assets.pinterest.com/ext/embed.html?id='.$matches[1];
    }

    private static function directVideo(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return preg_match('/\.(mp4|webm|ogg)$/i', $path) === 1 ? $url : null;
    }
}
