<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Role;
use App\Models\TeamInvite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeamInviteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $role = Role::query()->findOrFail($data['role_id']);
        abort_if($role->slug === 'administrator', 422, 'Administrator invites are not allowed.');

        $brandIds = collect($data['brand_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        foreach ($brandIds as $brandId) {
            abort_unless($request->user()->canAccessBrand($brandId), 403);
        }

        if ($brandIds === [] && ! $request->user()->isAdmin()) {
            $brandIds = $request->user()->accessibleBrandIds();
        }

        TeamInvite::query()->create([
            'token' => Str::random(48),
            'role_id' => $role->id,
            'brand_ids' => $brandIds,
            'created_by' => $request->user()->id,
            'expires_at' => isset($data['expires_in_days'])
                ? now()->addDays((int) $data['expires_in_days'])
                : now()->addDays(14),
            'max_uses' => $data['max_uses'] ?? 25,
            'uses_count' => 0,
        ]);

        return back()->with('success', 'Invite link created.');
    }

    public function destroy(Request $request, TeamInvite $invite): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $invite->update(['revoked_at' => now()]);

        return back()->with('success', 'Invite revoked.');
    }

    public function show(string $token): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $invite = $this->findOpenInvite($token);

        return Inertia::render('Auth/Join', [
            'token' => $invite->token,
            'role' => $invite->role?->only(['name', 'slug']),
            'organization' => \App\Models\Setting::getValue('organization_name', 'Prime Z'),
            'expires_at' => $invite->expires_at?->toIso8601String(),
        ]);
    }

    public function register(Request $request, string $token): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $invite = $this->findOpenInvite($token);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($data, $invite) {
            $locked = TeamInvite::query()->whereKey($invite->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages([
                    'email' => 'This invite is no longer valid.',
                ]);
            }

            $person = User::query()->create([
                'name' => $data['name'],
                'username' => Str::lower($data['username']),
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $person->roles()->sync([$locked->role_id]);

            $brandIds = collect($locked->brand_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => Brand::query()->whereKey($id)->exists())
                ->values()
                ->all();

            if ($brandIds !== []) {
                $person->brands()->sync($brandIds);
            }

            $locked->increment('uses_count');

            return $person;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_seen_at' => now()])->save();

        return redirect()->route('dashboard')->with('success', 'Welcome to the team.');
    }

    private function findOpenInvite(string $token): TeamInvite
    {
        $invite = TeamInvite::query()->with('role:id,name,slug')->where('token', $token)->first();

        if (! $invite || ! $invite->isOpen()) {
            abort(404, 'This invite link is invalid or has expired.');
        }

        return $invite;
    }
}
