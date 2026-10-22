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
            'lookups' => fn () => $user ? Lookups::shared() : null,
            'appName' => config('app.name'),
        ];
    }
}
