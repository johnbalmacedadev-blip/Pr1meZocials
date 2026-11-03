<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Google's Gemini API, which runs the Nano Banana image models.
 * https://ai.google.dev/gemini-api/docs/image-generation
 */
class GeminiImageClient
{
    private const API_REVISION = '2026-05-20';

    private const IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    public function model(): string
    {
        return (string) config('services.gemini.image_model');
    }

    /**
     * Looks up the model with this key. This does not generate anything, so it costs nothing.
     *
     * @throws GeminiImageException
     */
    public function verifyKey(string $key): void
    {
        try {
            $response = $this->request($key)->timeout(20)->get('/v1beta/models/'.$this->model());
        } catch (ConnectionException) {
            throw new GeminiImageException('Could not reach Google. Please try again.');
        }

        if (! $response->successful()) {
            throw new GeminiImageException($this->errorMessage($response));
        }
    }

    /**
     * @param  list<array{mime: string, bytes: string}>  $images
     * @return array{bytes: string, mime: string, width: int|null, height: int|null}
     *
     * @throws GeminiImageException
     */
    public function generate(string $key, string $prompt, array $images, string $aspectRatio, string $imageSize): array
    {
        $input = [['type' => 'text', 'text' => $prompt]];
        foreach ($images as $image) {
            $input[] = ['type' => 'image', 'mime_type' => $image['mime'], 'data' => base64_encode($image['bytes'])];
        }

        try {
            $response = $this->request($key)->timeout(180)->post('/v1beta/interactions', [
                'model' => $this->model(),
                'input' => $input,
                'response_format' => ['type' => 'image', 'aspect_ratio' => $aspectRatio, 'image_size' => $imageSize],
            ]);
        } catch (ConnectionException) {
            throw new GeminiImageException('Google took too long to answer. Please try again.');
        }

        if (! $response->successful()) {
            throw new GeminiImageException($this->errorMessage($response));
        }

        return $this->readImage((array) $response->json());
    }

    private function request(string $key): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.gemini.base_url'), '/'))
            ->connectTimeout(15)
            ->acceptJson()
            ->withHeaders(['x-goog-api-key' => $key, 'Api-Revision' => self::API_REVISION]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{bytes: string, mime: string, width: int|null, height: int|null}
     */
    private function readImage(array $body): array
    {
        $image = null;
        $text = [];

        foreach ((array) ($body['steps'] ?? []) as $step) {
            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }
            foreach ((array) ($step['content'] ?? []) as $block) {
                if (($block['type'] ?? null) === 'image' && ! empty($block['data'])) {
                    $image = $block;
                } elseif (($block['type'] ?? null) === 'text' && ! empty($block['text'])) {
                    $text[] = $block['text'];
                }
            }
        }

        $image ??= is_array($body['output_image'] ?? null) ? $body['output_image'] : null;

        if ($image === null) {
            // Usually a safety refusal, which the model explains in words.
            $reason = Str::limit(trim(implode(' ', $text)), 300);

            throw new GeminiImageException($reason !== ''
                ? 'Google did not make an image: '.$reason
                : 'Google did not make an image. Try describing it differently.');
        }

        $bytes = base64_decode((string) ($image['data'] ?? ''), true);
        $info = $bytes ? @getimagesizefromstring($bytes) : false;

        if ($info === false || ! in_array($info['mime'], self::IMAGE_MIMES, true)) {
            throw new GeminiImageException('Google sent back an image we could not read. Please try again.');
        }

        return ['bytes' => $bytes, 'mime' => $info['mime'], 'width' => $info[0], 'height' => $info[1]];
    }

    private function errorMessage(Response $response): string
    {
        $status = $response->status();
        $detail = Str::limit((string) $response->json('error.message'), 240);

        if ($status === 401 || $status === 403 || str_contains(strtolower($detail), 'api key')) {
            return 'Google rejected this API key. Check it in Google AI Studio, or connect a new one.';
        }
        if ($status === 429) {
            return 'Google says the limit on this API key has been reached. Image generation needs billing turned on in Google AI Studio. If it is on, wait a minute and try again.';
        }
        if ($status === 404) {
            return 'Google no longer offers the image model '.$this->model().'. The Studio setup needs updating.';
        }
        if ($status >= 500) {
            return 'Google is having a problem right now. Please try again in a moment.';
        }

        return $detail !== '' ? 'Google could not do this: '.$detail : 'Google could not do this. Please try again.';
    }
}
