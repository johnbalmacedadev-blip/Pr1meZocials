<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

class GoogleDriveFolder
{
    /**
     * @return list<array{id: string, name: string, kind: string, mime: string|null, modified: string|null, thumbnail: string|null}>
     */
    public function listFolder(string $folderId): array
    {
        $this->assertId($folderId);

        return Cache::remember('drive.folder.'.$folderId, now()->addMinutes(10), function () use ($folderId) {
            $response = Http::timeout(20)
                ->withHeaders(['Accept' => 'text/html'])
                ->get('https://drive.google.com/embeddedfolderview', ['id' => $folderId]);

            if (! $response->successful()) {
                throw new GoogleDriveException('This Google Drive folder could not be opened. Use View in Google if the folder is private.');
            }

            return $this->parse((string) $response->body());
        });
    }

    public function folderIsInside(string $rootId, string $folderId): bool
    {
        if (! $this->validId($rootId) || ! $this->validId($folderId)) {
            return false;
        }

        if ($rootId === $folderId) {
            return true;
        }

        $queue = [$rootId];
        $seen = [];

        while ($queue !== [] && count($seen) < 40) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;

            try {
                $entries = $this->listFolder($current);
            } catch (GoogleDriveException) {
                continue;
            }

            foreach ($entries as $entry) {
                if ($entry['kind'] !== 'folder') {
                    continue;
                }
                if ($entry['id'] === $folderId) {
                    return true;
                }
                $queue[] = $entry['id'];
            }
        }

        return false;
    }

    /**
     * @return array{id: string, name: string, kind: string, mime: string|null, modified: string|null, thumbnail: string|null}|null
     */
    public function findFile(string $rootId, string $folderId, string $fileId): ?array
    {
        if (! $this->validId($fileId) || ! $this->folderIsInside($rootId, $folderId)) {
            return null;
        }

        foreach ($this->listFolder($folderId) as $entry) {
            if ($entry['kind'] === 'file' && $entry['id'] === $fileId) {
                return $entry;
            }
        }

        return null;
    }

    public function idFromUrl(string $url): ?string
    {
        if (preg_match('#/folders/([a-zA-Z0-9_-]+)#', $url, $match) === 1) {
            return $match[1];
        }

        if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $url, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    public function validId(string $id): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]{8,128}$/', $id) === 1;
    }

    public function isPreviewable(?string $mime): bool
    {
        return is_string($mime) && (str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/'));
    }

    /**
     * @param  array{name: string, mime: string|null, thumbnail: string|null}  $file
     */
    public function fetchPreview(array $file, ?string $range): ResponseInterface
    {
        $thumbnail = $file['thumbnail'] ?? null;
        $mime = $file['mime'] ?? '';

        if (is_string($thumbnail) && $thumbnail !== '' && str_starts_with((string) $mime, 'image/')) {
            return $this->fetch($this->largerThumbnail($thumbnail), $this->thumbnailHosts(), $range);
        }

        return $this->fetch($this->downloadUrl($file['id']), $this->downloadHosts(), $range);
    }

    /**
     * @param  array{id: string}  $file
     */
    public function fetchDownload(array $file, ?string $range): ResponseInterface
    {
        return $this->fetch($this->downloadUrl($file['id']), $this->downloadHosts(), $range);
    }

    /**
     * @return list<array{id: string, name: string, kind: string, mime: string|null, modified: string|null, thumbnail: string|null}>
     */
    public function parse(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false || ($document->getElementById('flip-contents') === null && ! str_contains($html, 'flip-entries'))) {
            throw new GoogleDriveException('This Google Drive folder is not shared for viewing here. Share it so anyone with the link can view it, or use View in Google.');
        }

        $xpath = new DOMXPath($document);
        $entries = [];

        foreach ($xpath->query('//div[starts-with(@id, "entry-")]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $id = substr($node->getAttribute('id'), strlen('entry-'));
            if (! $this->validId($id)) {
                continue;
            }

            $title = trim($xpath->evaluate('string(.//div[contains(@class, "flip-entry-title")])', $node));
            $href = trim($xpath->evaluate('string(.//a[1]/@href)', $node));
            $modified = trim($xpath->evaluate('string(.//div[contains(@class, "flip-entry-last-modified")])', $node));
            $thumb = trim($xpath->evaluate('string(.//div[contains(@class, "flip-entry-thumb")]//img/@src)', $node));
            $icon = trim($xpath->evaluate('string(.//div[contains(@class, "flip-entry-list-icon")]//img/@src)', $node));
            $folder = str_contains($href, '/folders/') || $xpath->evaluate('string(.//*[@aria-label="Folder"]/@aria-label)', $node) === 'Folder';

            if ($title === '') {
                continue;
            }

            $entries[] = [
                'id' => $id,
                'name' => $title,
                'kind' => $folder ? 'folder' : 'file',
                'mime' => $folder ? null : $this->mimeFromIcon($icon, $title),
                'modified' => $modified !== '' ? preg_replace('/\s+/', ' ', $modified) : null,
                'thumbnail' => $this->safeThumbnail($thumb),
            ];
        }

        usort($entries, function (array $left, array $right): int {
            if ($left['kind'] !== $right['kind']) {
                return $left['kind'] === 'folder' ? -1 : 1;
            }

            return strcasecmp($left['name'], $right['name']);
        });

        return $entries;
    }

    private function assertId(string $folderId): void
    {
        if (! $this->validId($folderId)) {
            throw new GoogleDriveException('This Google Drive folder could not be opened.');
        }
    }

    private function downloadUrl(string $fileId): string
    {
        $this->assertId($fileId);

        return 'https://drive.usercontent.google.com/download?id='.rawurlencode($fileId).'&export=download&confirm=t';
    }

    /**
     * @param  list<string>  $hosts
     */
    private function fetch(string $url, array $hosts, ?string $range): ResponseInterface
    {
        $this->assertAllowedUrl($url, $hosts);

        $headers = ['Accept' => '*/*'];
        if (is_string($range) && preg_match('/^bytes=\d*-\d*$/', $range) === 1) {
            $headers['Range'] = $range;
        }

        $response = Http::timeout(120)
            ->withOptions([
                'stream' => true,
                'allow_redirects' => [
                    'max' => 5,
                    'protocols' => ['https'],
                    'referer' => false,
                    'on_redirect' => function ($request, $response, $uri) use ($hosts): void {
                        $this->assertAllowedUrl((string) $uri, $hosts);
                    },
                ],
            ])
            ->withHeaders($headers)
            ->get($url);

        $psr = $response->toPsrResponse();
        $type = strtolower($psr->getHeaderLine('Content-Type'));

        if ($psr->getStatusCode() >= 400 || str_contains($type, 'text/html')) {
            throw new GoogleDriveException('This file could not be downloaded from Google Drive.');
        }

        return $psr;
    }

    /**
     * @param  list<string>  $hosts
     */
    private function assertAllowedUrl(string $url, array $hosts): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || ! in_array($host, $hosts, true)) {
            throw new GoogleDriveException('This file could not be downloaded from Google Drive.');
        }
    }

    /**
     * @return list<string>
     */
    private function downloadHosts(): array
    {
        return [
            'drive.usercontent.google.com',
            'drive.google.com',
            'docs.google.com',
            'lh3.googleusercontent.com',
            'lh4.googleusercontent.com',
            'lh5.googleusercontent.com',
            'lh6.googleusercontent.com',
        ];
    }

    /**
     * @return list<string>
     */
    private function thumbnailHosts(): array
    {
        return [
            'lh3.googleusercontent.com',
            'lh4.googleusercontent.com',
            'lh5.googleusercontent.com',
            'lh6.googleusercontent.com',
        ];
    }

    private function largerThumbnail(string $url): string
    {
        if (preg_match('/=s\d+$/', $url) === 1) {
            return (string) preg_replace('/=s\d+$/', '=s1000', $url);
        }

        return $url.'=s1000';
    }

    private function safeThumbnail(string $src): ?string
    {
        if ($src === '') {
            return null;
        }

        if (str_starts_with($src, '//')) {
            $src = 'https:'.$src;
        }

        $parts = parse_url($src);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || ! in_array($host, $this->thumbnailHosts(), true)) {
            return null;
        }

        if (! str_contains((string) ($parts['path'] ?? ''), '/drive-storage/')) {
            return null;
        }

        return $src;
    }

    private function mimeFromIcon(string $icon, string $name): ?string
    {
        if (preg_match('#/type/([a-z0-9.+-]+/[a-z0-9.+-]+)#i', $icon, $match) === 1) {
            return strtolower($match[1]);
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            default => null,
        };
    }
}
