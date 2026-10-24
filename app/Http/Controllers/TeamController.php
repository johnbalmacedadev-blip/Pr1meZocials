<?php

namespace App\Http\Controllers;

use App\Http\Controllers\TaskNoteController;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\PermissionPreset;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Support\ContentPresenter;
use App\Support\PageAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->hasPermission('team.view')) {
            return redirect()->route('dashboard');
        }

        $from = Carbon::parse($request->input('from', now()->subDays(29)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        $brandIds = $request->user()->accessibleBrandIds();

        $people = User::query()
            ->with('roles:id,name,slug')
            ->where('is_active', true)
            ->when(! $request->user()->isAdmin(), fn (Builder $query) => $query->whereHas('brands', fn (Builder $brands) => $brands->whereIn('brands.id', $brandIds ?: [0])))
            ->orderBy('name')
            ->get();

        $statusIds = WorkflowStatus::query()->pluck('slug', 'id');
        $rows = ContentItem::query()
            ->visibleTo($request->user())
            ->whereIn('owner_id', $people->pluck('id')->all() ?: [0])
            ->get(['id', 'owner_id', 'status_id', 'deadline', 'published_at', 'complexity', 'title', 'reference_number']);

        $viewerId = $request->user()->id;
        $viewerIsAdmin = $request->user()->isAdmin();
        $members = $people->map(function (User $person) use ($rows, $statusIds, $from, $to, $viewerId, $viewerIsAdmin) {
            $owned = $rows->where('owner_id', $person->id);
            $open = $owned->filter(fn (ContentItem $item) => ! in_array($statusIds[$item->status_id] ?? '', config('ops.closed_statuses'), true));
            $complexity = $open->whereNotNull('complexity');

            return [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'username' => $person->username,
                'job_title' => $person->job_title,
                'roles' => $person->roles->map->only(['id', 'name', 'slug'])->values(),
                'can_edit' => $viewerIsAdmin || ! $person->isAdmin(),
                'can_delete' => $viewerId !== $person->id && ($viewerIsAdmin || ! $person->isAdmin()),
                'active' => $open->count(),
                'in_progress' => $open->filter(fn (ContentItem $item) => ($statusIds[$item->status_id] ?? '') === 'in_progress')->count(),
                'awaiting_review' => $open->filter(fn (ContentItem $item) => ($statusIds[$item->status_id] ?? '') === 'internal_review')->count(),
                'overdue' => $open->filter(fn (ContentItem $item) => $item->deadline && $item->deadline->copy()->endOfDay()->isPast())->count(),
                'completed' => $owned->filter(fn (ContentItem $item) => $item->published_at && $item->published_at->between($from, $to))->count(),
                'upcoming' => $open->filter(fn (ContentItem $item) => $item->deadline && $item->deadline->between(now()->startOfDay(), now()->addDays(14)))->count(),
                'average_complexity' => $complexity->isEmpty() ? null : round($complexity->avg('complexity'), 1),
            ];
        })->values();

        $roleModels = Role::query()
            ->with('permissions:id,slug')
            ->when(! $request->user()->isAdmin(), fn (Builder $query) => $query->where('slug', '!=', 'administrator'))
            ->orderBy('name')
            ->get();

        $canManageMembers = $request->user()->hasPermission('team.create')
            || $request->user()->hasPermission('team.update')
            || $request->user()->hasPermission('users.manage');

        $canManageRoles = $request->user()->isAdmin()
            || $request->user()->hasPermission('users.manage')
            || $request->user()->hasPermission('settings.manage')
            || $request->user()->hasPermission('settings.update');

        return Inertia::render('Team/Index', [
            'members' => $members,
            'roles' => $roleModels->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                ...PageAccess::decode($role->permissions->pluck('slug')->all()),
            ])->values(),
            'canManage' => $canManageMembers,
            'canManageRoles' => $canManageRoles,
            'pageAccess' => [
                'pages' => PageAccess::pages(),
                'actions' => PageAccess::actionLabels(),
                'extras' => PageAccess::extras(),
            ],
            'presets' => PermissionPreset::query()
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get()
                ->map(fn (PermissionPreset $preset) => [
                    'id' => $preset->id,
                    'name' => $preset->name,
                    'slug' => $preset->slug,
                    'description' => $preset->description,
                    'matrix' => $preset->matrix ?? [],
                    'extras' => $preset->extras ?? [],
                    'is_system' => $preset->is_system,
                ])
                ->values(),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validatedMember($request);
        $person = User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'job_title' => $data['job_title'],
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $person->roles()->sync($data['role_ids']);
        $person->brands()->sync($this->brandIds($request));

        return back()->with('success', 'Team member added.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->authorizeMember($request, $user);

        $data = $this->validatedMember($request, $user);
        $keepsAdmin = Role::query()->whereIn('id', $data['role_ids'])->where('slug', 'administrator')->exists();
        $this->guardLastAdmin($user, $keepsAdmin);

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'job_title' => $data['job_title'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->roles()->sync($data['role_ids']);

        return back()->with('success', 'Team member updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->authorizeMember($request, $user);

        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot remove your own account.');
        }

        $this->guardLastAdmin($user, false);
        $user->forceFill(['is_active' => false])->save();
        $user->delete();

        return back()->with('success', 'Team member removed.');
    }

    public function show(Request $request, User $user, ContentPresenter $presenter): Response|RedirectResponse
    {
        if (! $request->user()->hasPermission('team.view')) {
            return redirect()->route('dashboard');
        }
        abort_unless($request->user()->isAdmin() || $user->brands()->whereIn('brands.id', $request->user()->accessibleBrandIds() ?: [0])->exists(), 403);

        $items = ContentItem::query()
            ->visibleTo($request->user())
            ->where(function (Builder $query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->orWhere('copywriter_id', $user->id)
                    ->orWhere('video_editor_id', $user->id)
                    ->orWhere('reviewer_id', $user->id)
                    ->orWhereHas('collaborators', fn (Builder $collaborators) => $collaborators->where('users.id', $user->id));
            })
            ->with(['brand:id,name', 'campaign:id,name', 'status:id,name,slug,color', 'owner:id,name', 'platformVersions.platform:id,name,slug,color'])
            ->latest('updated_at')
            ->limit(50)
            ->get();

        return Inertia::render('Team/Show', [
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'job_title' => $user->job_title,
                'roles' => $user->roles()->get(['name', 'slug']),
            ],
            'content' => $items->map(fn (ContentItem $item) => $presenter->summary($item))->values(),
            'notes' => TaskNoteController::pendingFor($request->user(), $user),
            'canSendNote' => $request->user()->hasPermission('team.view'),
        ]);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless(
            $request->user()->hasPermission('team.create')
            || $request->user()->hasPermission('team.update')
            || $request->user()->hasPermission('users.manage'),
            403
        );
    }

    private function authorizeMember(Request $request, User $user): void
    {
        abort_unless(
            $request->user()->isAdmin() || $user->brands()->whereIn('brands.id', $request->user()->accessibleBrandIds() ?: [0])->exists(),
            403,
        );
        abort_if($user->isAdmin() && ! $request->user()->isAdmin(), 403);
    }

    /**
     * @return array{name: string, username: ?string, email: string, password: ?string, job_title: ?string, role_ids: list<int>}
     */
    private function validatedMember(Request $request, ?User $user = null): array
    {
        $request->merge([
            'username' => trim((string) $request->input('username', '')) ?: null,
            'job_title' => trim((string) $request->input('job_title', '')) ?: null,
            'password' => $request->filled('password') ? $request->input('password') : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')->ignore($user?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::defaults()],
            'job_title' => ['nullable', 'string', 'max:120'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        if (! $request->user()->isAdmin()) {
            $adminId = Role::query()->where('slug', 'administrator')->value('id');
            if (in_array((int) $adminId, array_map('intval', $data['role_ids']), true)) {
                throw ValidationException::withMessages([
                    'role_ids' => 'Only an administrator can assign that role.',
                ]);
            }
        }

        $username = trim((string) ($data['username'] ?? ''));

        return [
            'name' => trim($data['name']),
            'username' => $username === '' ? null : $username,
            'email' => $data['email'],
            'password' => $data['password'] ?? null,
            'job_title' => trim((string) ($data['job_title'] ?? '')) ?: null,
            'role_ids' => array_map('intval', $data['role_ids']),
        ];
    }

    /**
     * @return list<int>
     */
    private function brandIds(Request $request): array
    {
        $prime = Brand::query()->where('name', 'Prime Z')->where('is_active', true)->value('id');

        if ($prime && ($request->user()->isAdmin() || $request->user()->canAccessBrand((int) $prime))) {
            return [(int) $prime];
        }

        return array_slice($request->user()->accessibleBrandIds(), 0, 1);
    }

    private function guardLastAdmin(User $user, bool $remainsAdmin): void
    {
        if (! $user->isAdmin() || ! $user->is_active) {
            return;
        }

        $activeAdmins = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('slug', 'administrator'))
            ->count();

        if (! $remainsAdmin && $activeAdmins <= 1) {
            throw ValidationException::withMessages([
                'role_ids' => 'At least one active administrator is required.',
            ]);
        }
    }
}
