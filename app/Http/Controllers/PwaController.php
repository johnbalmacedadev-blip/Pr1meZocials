<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\Setting;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Installable app (web app manifest + service worker) and push subscriptions.
 * Both files are served by Laravel so they live at the app root (/pr1mesocials/...)
 * and the service worker can control every page.
 */
class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $name = (string) Setting::getValue('organization_name', config('app.name', 'Prime Z'));
        $root = rtrim(url('/'), '/');

        return response()->json([
            'id' => $root.'/',
            'name' => $name,
            'short_name' => $name,
            'description' => 'Creative operations: content, events, team chat and notifications.',
            'start_url' => $root.'/dashboard?source=pwa',
            'scope' => $root.'/',
            'display' => 'standalone',
            // Shared links (e.g. a ticket sent over Messenger) open in the installed app where supported.
            'handle_links' => 'preferred',
            'launch_handler' => ['client_mode' => ['navigate-existing', 'auto']],
            'orientation' => 'portrait',
            'background_color' => '#ffffff',
            'theme_color' => '#ffffff',
            'icons' => [
                ['src' => $root.'/brand/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $root.'/brand/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $root.'/brand/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            // Lets Chrome tell the page the app is already installed on this phone.
            'related_applications' => [['platform' => 'webapp', 'url' => $root.'/manifest.webmanifest']],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    public function serviceWorker(): Response
    {
        return response(file_get_contents(resource_path('pwa/sw.js')), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => rtrim(parse_url(url('/'), PHP_URL_PATH) ?: '', '/').'/',
            // Browsers re-check the worker on every visit; never let a proxy pin an old copy.
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function key(WebPushService $push): JsonResponse
    {
        $key = $push->publicKey();
        abort_unless($key, 503, 'Push notifications are not available on this server.');

        return response()->json(['public_key' => $key]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:2000', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'in:aes128gcm,aesgcm'],
        ]);

        // One row per device endpoint; a device that changes hands moves to the new user.
        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ],
        );

        return response()->json(['subscribed' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);

        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))
            ->delete();

        return response()->json(['subscribed' => false]);
    }

    public function test(Request $request, WebPushService $push): RedirectResponse
    {
        $user = $request->user();

        if (! $user->pushSubscriptions()->exists()) {
            return back()->with('error', 'No phone or browser has push notifications turned on for your account yet.');
        }

        $push->queue([$user->id], [
            'title' => 'Test notification',
            'body' => 'Push notifications are working on this device.',
            'url' => route('profile.edit'),
            'tag' => 'test-push',
        ]);
        $sent = $push->flush();

        return $sent > 0
            ? back()->with('success', 'Test notification sent to '.$sent.' device'.($sent === 1 ? '' : 's').'.')
            : back()->with('error', 'The push service did not accept the test. Turn notifications off and on again on your phone, then retry.');
    }
}
