<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Chunked video uploads for Compose. Shared hosting caps a single request at
 * post_max_size (often 8–64 MB), so the browser sends a large video in small
 * pieces that are appended on disk, then posts the finished upload id instead
 * of the file itself.
 */
class MediaUploadController extends Controller
{
    private const DIR = 'chunked-uploads';

    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'm4v'];

    public function start(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('content.publish'), 403);

        $maxBytes = (int) config('ops.video_upload_max_mb', 1024) * 1024 * 1024;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.$maxBytes],
        ], [
            'size.max' => 'Videos can be up to '.config('ops.video_upload_max_mb', 1024).' MB.',
        ]);

        $extension = strtolower(pathinfo($data['name'], PATHINFO_EXTENSION));
        if (! in_array($extension, self::VIDEO_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['name' => 'Upload an MP4, MOV, or M4V video.']);
        }

        $this->pruneStale($request->user()->id);

        $id = (string) Str::uuid();
        $this->writeMeta($request->user()->id, $id, [
            'name' => $data['name'],
            'size' => (int) $data['size'],
            'extension' => $extension,
            'received' => 0,
            'path' => null,
        ]);
        Storage::disk('local')->put($this->partPath($request->user()->id, $id), '');

        return response()->json(['upload_id' => $id, 'chunk_size' => $this->chunkSize()]);
    }

    public function chunk(Request $request, string $upload): JsonResponse
    {
        $userId = $request->user()->id;
        $meta = $this->meta($userId, $upload);
        abort_if($meta['path'] !== null, 409, 'This upload is already finished.');

        $request->validate([
            'offset' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:'.(int) ceil($this->chunkSize() / 1024) + 64],
        ]);

        // Chunks must arrive in order; a repeated chunk (retry after a timeout) is acknowledged, not appended.
        $offset = $request->integer('offset');
        if ($offset < $meta['received']) {
            return response()->json(['received' => $meta['received']]);
        }
        abort_if($offset !== $meta['received'], 409, 'Upload is out of order. Please try again.');

        $bytes = (int) $request->file('chunk')->getSize();
        abort_if($meta['received'] + $bytes > $meta['size'], 422, 'Upload is larger than announced.');

        $source = fopen($request->file('chunk')->getRealPath(), 'rb');
        $target = fopen(Storage::disk('local')->path($this->partPath($userId, $upload)), 'ab');
        stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);

        $meta['received'] += $bytes;
        $this->writeMeta($userId, $upload, $meta);

        return response()->json(['received' => $meta['received']]);
    }

    public function finish(Request $request, string $upload): JsonResponse
    {
        $userId = $request->user()->id;
        $meta = $this->meta($userId, $upload);

        if ($meta['path'] === null) {
            $part = $this->partPath($userId, $upload);
            abort_if($meta['received'] !== $meta['size'] || Storage::disk('local')->size($part) !== $meta['size'], 422, 'The upload is incomplete. Please try again.');

            $mime = (string) (mime_content_type(Storage::disk('local')->path($part)) ?: '');
            if (! str_starts_with($mime, 'video/') && $mime !== 'application/octet-stream') {
                Storage::disk('local')->delete($part);
                throw ValidationException::withMessages(['media' => 'That file is not a video.']);
            }

            $final = 'social-posts/'.$userId.'/'.$upload.'.'.$meta['extension'];
            Storage::disk('local')->move($part, $final);
            $meta['path'] = $final;
            $this->writeMeta($userId, $upload, $meta);
        }

        return response()->json(['upload_id' => $upload]);
    }

    /**
     * Finished upload for Compose: [path, original name]. The upload record is consumed.
     *
     * @return array{path: string, name: string}
     */
    public static function claim(int $userId, string $upload): array
    {
        $controller = new self;
        $meta = $controller->meta($userId, $upload);
        if ($meta['path'] === null || ! Storage::disk('local')->exists($meta['path'])) {
            throw ValidationException::withMessages(['media' => 'The video upload was not finished. Please attach it again.']);
        }
        Storage::disk('local')->delete($controller->metaPath($userId, $upload));

        return ['path' => $meta['path'], 'name' => $meta['name']];
    }

    /**
     * Largest piece that fits under this server's request limits, between 1 MB and 8 MB.
     */
    private function chunkSize(): int
    {
        $limit = min(self::iniBytes((string) ini_get('upload_max_filesize')), self::iniBytes((string) ini_get('post_max_size')));
        $limit = $limit > 0 ? $limit - 256 * 1024 : 8 * 1024 * 1024;

        return max(1024 * 1024, min(8 * 1024 * 1024, $limit));
    }

    /**
     * @return array{name: string, size: int, extension: string, received: int, path: string|null}
     */
    private function meta(int $userId, string $upload): array
    {
        abort_unless(Str::isUuid($upload), 404);
        $path = $this->metaPath($userId, $upload);
        abort_unless(Storage::disk('local')->exists($path), 404, 'Upload not found. Please attach the video again.');

        return json_decode((string) Storage::disk('local')->get($path), true);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function writeMeta(int $userId, string $upload, array $meta): void
    {
        Storage::disk('local')->put($this->metaPath($userId, $upload), json_encode($meta));
    }

    private function metaPath(int $userId, string $upload): string
    {
        return self::DIR.'/'.$userId.'/'.$upload.'.json';
    }

    private function partPath(int $userId, string $upload): string
    {
        return self::DIR.'/'.$userId.'/'.$upload.'.part';
    }

    /** Abandoned uploads older than a day are removed so they do not fill the disk. */
    private function pruneStale(int $userId): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files(self::DIR.'/'.$userId) as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
