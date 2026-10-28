<?php

namespace App\Http\Middleware;

use App\Support\Lookups;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user) {
            $user->loadMissing(['roles.permissions', 'brands:id,name']);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'job_title' => $user->job_title,
                    'roles' => $user->roles->map->only(['name', 'slug'])->values(),
                    'permissions' => $user->permissionSlugs(),
                    'brands' => $user->accessibleBrands()->map->only(['id', 'name'])->values(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'unreadNotifications' => fn () => $user ? $user->userNotifications()->whereNull('read_at')->count() : 0,
            'recentNotifications' => fn () => $user
                ? $user->userNotifications()->latest()->limit(8)->get(['id', 'title', 'body', 'read_at', 'created_at'])->map(fn ($note) => [
                    'id' => $note->id,
                    'title' => $note->title,
                    'body' => $note->body,
                    'read_at' => $note->read_at?->toIso8601String(),
                    'created_at' => $note->created_at?->toIso8601String(),
                ])->values()
                : [],
            'lookups' => fn () => $user ? Lookups::shared() : null,
            'appName' => config('app.name'),
            'basePath' => rtrim(parse_url((string) config('app.url'), PHP_URL_PATH) ?: '', '/'),
            // Public app id, used by the Share button's Messenger send dialog on computers.
            'facebookAppId' => fn () => $request->user() ? \App\Support\FacebookCredentials::appId() : '',
        ];
    }
}
