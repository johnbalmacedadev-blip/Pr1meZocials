<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($credentials['email']);
        $account = User::query()
            ->when(
                str_contains($login, '@'),
                fn ($query) => $query->where('email', $login),
                fn ($query) => $query->where('username', $login),
            )
            ->first();

        $authenticated = $account && Auth::attempt([
            'email' => $account->email,
            'password' => $credentials['password'],
        ], $request->boolean('remember'));

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if (! $request->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account is inactive. Contact an administrator.',
            ]);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_seen_at' => now()])->save();

        $landing = redirect()->intended(route('dashboard'));
        if ($this->landsOnForbiddenPage($request->user(), $landing->getTargetUrl())) {
            return redirect()->route('dashboard');
        }

        return $landing;
    }

    private function landsOnForbiddenPage(User $user, string $url): bool
    {
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        if (str_starts_with($path, '/team')) {
            return ! $user->hasPermission('team.view');
        }

        if (str_starts_with($path, '/settings')) {
            return ! $user->hasPermission('users.view')
                && ! $user->hasPermission('users.manage')
                && ! $user->hasPermission('brands.manage')
                && ! $user->hasPermission('platforms.manage')
                && ! $user->hasPermission('workflow.manage')
                && ! $user->hasPermission('taxonomy.manage')
                && ! $user->hasPermission('tags.manage')
                && ! $user->hasPermission('settings.manage');
        }

        return false;
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
