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
        $chunkSize = $this->chunkSize();
        $this->writeMeta($request->user()->id, $id, [
            'name' => $data['name'],
            'size' => (int) $data['size'],
            'extension' => $extension,
            'chunk_size' => $chunkSize,
            'chunks' => (int) ceil($data['size'] / $chunkSize),
            'path' => null,
        ]);

        return response()->json(['upload_id' => $id, 'chunk_size' => $chunkSize]);
    }

    /**
     * Stores one numbered piece. Pieces may arrive in any order and in parallel, and a
     * retried piece simply overwrites itself; finish() joins them in order.
     */
    public function chunk(Request $request, string $upload): JsonResponse
    {
        $userId = $request->user()->id;
        $meta = $this->meta($userId, $upload);
        abort_if($meta['path'] !== null, 409, 'This upload is already finished.');

        $request->validate([
            'index' => ['required', 'integer', 'min:0', 'max:'.($meta['chunks'] - 1)],
            'chunk' => ['required', 'file'],
        ]);

        $index = $request->integer('index');
        $expected = min($meta['chunk_size'], $meta['size'] - $index * $meta['chunk_size']);
        abort_if((int) $request->file('chunk')->getSize() !== $expected, 422, 'Upload piece has the wrong size. Please try again.');

        $request->file('chunk')->storeAs($this->piecesDir($userId, $upload), $index.'.part', 'local');

        return response()->json(['index' => $index]);
    }

    public function finish(Request $request, string $upload): JsonResponse
    {
        $userId = $request->user()->id;
        $meta = $this->meta($userId, $upload);

        if ($meta['path'] === null) {
            $disk = Storage::disk('local');
            $dir = $this->piecesDir($userId, $upload);

            for ($index = 0; $index < $meta['chunks']; $index++) {
                $expected = min($meta['chunk_size'], $meta['size'] - $index * $meta['chunk_size']);
                abort_unless($disk->exists($dir.'/'.$index.'.part') && $disk->size($dir.'/'.$index.'.part') === $expected, 422, 'The upload is incomplete. Please try again.');
            }

            $mime = (string) (mime_content_type($disk->path($dir.'/0.part')) ?: '');
            if (! str_starts_with($mime, 'video/') && $mime !== 'application/octet-stream') {
                $disk->deleteDirectory($dir);
                throw ValidationException::withMessages(['media' => 'That file is not a video.']);
            }

            $final = 'social-posts/'.$userId.'/'.$upload.'.'.$meta['extension'];
            $disk->makeDirectory(dirname($final));
            $target = fopen($disk->path($final), 'wb');
            for ($index = 0; $index < $meta['chunks']; $index++) {
                $source = fopen($disk->path($dir.'/'.$index.'.part'), 'rb');
                stream_copy_to_stream($source, $target);
                fclose($source);
            }
            fclose($target);
            $disk->deleteDirectory($dir);

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
     * Largest piece that fits under this server's request limits, between 1 MB and 16 MB.
     * Fewer, bigger pieces mean fewer round trips to a far-away server.
     */
    private function chunkSize(): int
    {
        $limit = min(self::iniBytes((string) ini_get('upload_max_filesize')), self::iniBytes((string) ini_get('post_max_size')));
        $cap = max(1, (int) config('ops.video_chunk_max_mb', 16)) * 1024 * 1024;
        $limit = $limit > 0 ? $limit - 256 * 1024 : $cap;

        return max(1024 * 1024, min($cap, $limit));
    }

    /**
     * @return array{name: string, size: int, extension: string, chunk_size: int, chunks: int, path: string|null}
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

    private function piecesDir(int $userId, string $upload): string
    {
        return self::DIR.'/'.$userId.'/'.$upload;
    }

    /** Abandoned uploads older than a day are removed so they do not fill the disk. */
    private function pruneStale(int $userId): void
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDay()->getTimestamp();
        foreach ($disk->files(self::DIR.'/'.$userId) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $disk->deleteDirectory(substr($file, 0, -strlen('.json')));
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
