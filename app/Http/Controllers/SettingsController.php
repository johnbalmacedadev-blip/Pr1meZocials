<?php

namespace App\Http\Controllers;

use App\Models\AudienceSegment;
use App\Models\Brand;
use App\Models\ContentObjective;
use App\Models\ContentPillar;
use App\Models\Platform;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\Tag;
use App\Models\TeamInvite;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Rules\HttpUrl;
use App\Support\Lookups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canOpen($user), 403);

        return Inertia::render('Settings/Index', [
            'section' => $request->string('section')->toString() ?: 'general',
            'users' => $user->hasPermission('users.view') || $user->hasPermission('users.manage')
                ? User::query()
                    ->with(['roles:id,name,slug', 'brands:id,name', 'socialAccounts:id,name,brand_id'])
                    ->when(! $user->isAdmin(), fn ($query) => $query->where(function ($inner) use ($user) {
                        $inner->whereHas('brands', fn ($brands) => $brands->whereIn('brands.id', $user->accessibleBrandIds() ?: [0]))
                            ->orWhere('users.id', $user->id);
                    }))
                    ->orderBy('name')
                    ->get()
                    ->map(fn (User $person) => [
                        'id' => $person->id,
                        'name' => $person->name,
                        'email' => $person->email,
                        'job_title' => $person->job_title,
                        'is_active' => $person->is_active,
                        'roles' => $person->roles->map->only(['id', 'name', 'slug'])->values(),
                        'brands' => $person->brands->map->only(['id', 'name'])->values(),
                        'social_account_ids' => $person->socialAccounts->pluck('id')->map(fn ($id) => (int) $id)->values(),
                        'social_accounts' => $person->socialAccounts->map->only(['id', 'name'])->values(),
                    ])
                : [],
            'roles' => Role::query()->orderBy('name')->get(['id', 'name', 'slug', 'description']),
            'brands' => Brand::query()
                ->when(! $user->isAdmin(), fn ($query) => $query->whereIn('id', $user->accessibleBrandIds() ?: [0]))
                ->orderBy('name')
                ->get(),
            'facebookPages' => ($user->hasPermission('users.view') || $user->hasPermission('users.manage'))
                ? SocialAccount::query()
                    ->with('brand:id,name')
                    ->whereHas('platform', fn ($query) => $query->where('slug', 'facebook'))
                    ->when(! $user->isAdmin(), fn ($query) => $query->whereIn('brand_id', $user->accessibleBrandIds() ?: [0]))
                    ->orderBy('name')
                    ->get()
                    ->map(fn (SocialAccount $account) => [
                        'id' => $account->id,
                        'name' => $account->name,
                        'brand_id' => $account->brand_id,
                        'brand_name' => $account->brand?->name,
                        'is_active' => $account->is_active,
                    ])
                : [],
            'platforms' => $user->hasPermission('platforms.manage') ? Platform::query()->orderBy('sort_order')->get() : [],
            'statuses' => $user->hasPermission('workflow.manage') ? WorkflowStatus::query()->orderBy('sort_order')->get() : [],
            'pillars' => ContentPillar::query()->orderBy('name')->get(),
            'objectives' => ContentObjective::query()->orderBy('name')->get(),
            'segments' => AudienceSegment::query()->with('brand:id,name')->orderBy('name')->get(),
            'tags' => Tag::query()->with('brand:id,name')->orderBy('name')->get(),
            'settings' => [
                'deadline_warning_days' => Setting::getValue('deadline_warning_days', config('ops.deadline_warning_days')),
                'organization_name' => Setting::getValue('organization_name', 'Prime Z'),
            ],
            'invites' => $user->hasPermission('users.manage')
                ? TeamInvite::query()
                    ->with(['role:id,name,slug', 'creator:id,name'])
                    ->latest()
                    ->limit(20)
                    ->get()
                    ->map(fn (TeamInvite $invite) => [
                        'id' => $invite->id,
                        'url' => $invite->joinUrl(),
                        'role' => $invite->role?->only(['id', 'name', 'slug']),
                        'brand_ids' => $invite->brand_ids ?? [],
                        'expires_at' => $invite->expires_at?->toIso8601String(),
                        'max_uses' => $invite->max_uses,
                        'uses_count' => $invite->uses_count,
                        'revoked_at' => $invite->revoked_at?->toIso8601String(),
                        'is_open' => $invite->isOpen(),
                        'created_by' => $invite->creator?->name,
                        'created_at' => $invite->created_at?->toIso8601String(),
                    ])
                : [],
            'abilities' => [
                'users' => $user->hasPermission('users.manage'),
                'brands' => $user->hasPermission('brands.manage'),
                'platforms' => $user->hasPermission('platforms.manage'),
                'workflow' => $user->hasPermission('workflow.manage'),
                'taxonomy' => $user->hasPermission('taxonomy.manage'),
                'tags' => $user->hasPermission('tags.manage'),
                'settings' => $user->hasPermission('settings.manage'),
            ],
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'job_title' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
            'social_account_ids' => ['nullable', 'array'],
            'social_account_ids.*' => ['integer', 'exists:social_accounts,id'],
        ]);

        $pageIds = $this->validatedSocialAccountIds($request->user(), $data['social_account_ids'] ?? [], $data['brand_ids'] ?? []);

        $person = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'job_title' => $data['job_title'] ?? null,
            'is_active' => $data['is_active'],
            'email_verified_at' => now(),
        ]);
        $person->roles()->sync($data['role_ids']);
        $person->brands()->sync($data['brand_ids'] ?? []);
        $person->socialAccounts()->sync($pageIds);

        return back()->with('success', 'User created.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
            'job_title' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
            'social_account_ids' => ['nullable', 'array'],
            'social_account_ids.*' => ['integer', 'exists:social_accounts,id'],
        ]);

        $adminRoleId = Role::query()->where('slug', 'administrator')->value('id');
        $keepsAdmin = in_array($adminRoleId, array_map('intval', $data['role_ids']), true) && $data['is_active'];
        $activeAdmins = User::query()->where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', 'administrator'))->count();

        if ($user->isAdmin() && $user->is_active && ! $keepsAdmin && $activeAdmins <= 1) {
            throw ValidationException::withMessages([
                'role_ids' => 'At least one active administrator is required.',
            ]);
        }

        $pageIds = $this->validatedSocialAccountIds($request->user(), $data['social_account_ids'] ?? [], $data['brand_ids'] ?? []);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'job_title' => $data['job_title'] ?? null,
            'is_active' => $data['is_active'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->roles()->sync($data['role_ids']);
        $user->brands()->sync($data['brand_ids'] ?? []);
        $user->socialAccounts()->sync($pageIds);

        return back()->with('success', 'User updated.');
    }

    public function storeBrand(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('brands.manage'), 403);
        $data = $this->brandData($request);
        $brand = Brand::query()->create([
            ...$data,
            'slug' => $this->uniqueSlug(Brand::class, $data['name']),
        ]);

        if (! $request->user()->isAdmin()) {
            $request->user()->brands()->syncWithoutDetaching([$brand->id]);
        }

        return back()->with('success', 'Brand created.');
    }

    public function updateBrand(Request $request, Brand $brand): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('brands.manage'), 403);
        abort_unless($request->user()->canAccessBrand($brand->id), 403);
        $brand->update($this->brandData($request));

        return back()->with('success', 'Brand updated.');
    }

    public function destroyBrand(Request $request, Brand $brand): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('brands.manage'), 403);
        abort_unless($request->user()->canAccessBrand($brand->id), 403);

        $brand->delete();

        return back()->with('success', 'Brand deleted.');
    }

    public function storePlatform(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('platforms.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        Platform::query()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(Platform::class, $data['name']),
            'color' => $data['color'] ?: 'zinc',
            'is_custom' => true,
            'is_active' => true,
            'sort_order' => (int) Platform::query()->max('sort_order') + 1,
        ]);
        Lookups::flush();

        return back()->with('success', 'Platform added.');
    }

    public function updatePlatform(Request $request, Platform $platform): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('platforms.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100'],
        ]);
        $platform->update($data);
        Lookups::flush();

        return back()->with('success', 'Platform updated.');
    }

    public function destroyPlatform(Request $request, Platform $platform): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('platforms.manage'), 403);

        if (! $platform->is_custom) {
            throw ValidationException::withMessages([
                'platform' => 'Built-in platforms cannot be deleted. Hide them instead.',
            ]);
        }

        if ($platform->socialAccounts()->exists() || $platform->versions()->exists()) {
            throw ValidationException::withMessages([
                'platform' => 'This platform is in use and cannot be deleted.',
            ]);
        }

        $platform->delete();
        Lookups::flush();

        return back()->with('success', 'Platform deleted.');
    }

    public function updateWorkflow(Request $request, WorkflowStatus $status): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('workflow.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'string', 'max:20'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100'],
            'allowed_transitions' => ['array'],
            'allowed_transitions.*' => ['string', 'exists:workflow_statuses,slug', Rule::notIn([$status->slug])],
        ]);
        $status->update($data);
        Lookups::flush();

        return back()->with('success', 'Workflow status updated.');
    }

    public function storePillar(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('taxonomy.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:500']]);
        ContentPillar::query()->create([...$data, 'slug' => $this->uniqueSlug(ContentPillar::class, $data['name'])]);

        return back()->with('success', 'Content pillar added.');
    }

    public function updatePillar(Request $request, ContentPillar $pillar): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('taxonomy.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:500']]);
        $pillar->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'slug' => $pillar->name === $data['name'] ? $pillar->slug : $this->uniqueSlug(ContentPillar::class, $data['name'], $pillar->id),
        ]);

        return back()->with('success', 'Content pillar updated.');
    }

    public function destroyPillar(Request $request, ContentPillar $pillar): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('taxonomy.manage'), 403);
        $pillar->delete();

        return back()->with('success', 'Content pillar deleted.');
    }

    public function storeObjective(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('taxonomy.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:500']]);
        ContentObjective::query()->create([...$data, 'slug' => $this->uniqueSlug(ContentObjective::class, $data['name'])]);

        return back()->with('success', 'Objective added.');
    }

    public function updateObjective(Request $request, ContentObjective $objective): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('taxonomy.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:500']]);
        $objective->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'slug' => $objective->name === $data['name'] ? $objective->slug : $this->uniqueSlug(ContentObjective::class, $data['name'], $objective->id),
        ]);

        return back()->with('success', 'Objective updated.');
    }

    public function destroyObjective(Request $request, ContentObjective $objective): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('taxonomy.manage'), 403);
        $objective->delete();

        return back()->with('success', 'Objective deleted.');
    }

    public function storeSegment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('taxonomy.manage'), 403);
        $data = $this->segmentData($request);
        AudienceSegment::query()->create($data);

        return back()->with('success', 'Audience segment added.');
    }

    public function updateSegment(Request $request, AudienceSegment $segment): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('taxonomy.manage'), 403);
        $segment->update($this->segmentData($request));

        return back()->with('success', 'Audience segment updated.');
    }

    public function destroySegment(Request $request, AudienceSegment $segment): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('taxonomy.manage'), 403);
        $segment->delete();

        return back()->with('success', 'Audience segment deleted.');
    }

    public function storeTag(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('tags.manage'), 403);
        $data = $this->tagData($request);
        Tag::query()->create($data);

        return back()->with('success', 'Tag added.');
    }

    public function updateTag(Request $request, Tag $tag): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('tags.manage'), 403);
        $tag->update($this->tagData($request, $tag->id));

        return back()->with('success', 'Tag updated.');
    }

    public function destroyTag(Request $request, Tag $tag): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('tags.manage'), 403);
        $tag->delete();

        return back()->with('success', 'Tag deleted.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:120'],
            'deadline_warning_days' => ['required', 'integer', 'min:1', 'max:30'],
        ]);
        Setting::putValue('organization_name', $data['organization_name']);
        Setting::putValue('deadline_warning_days', (int) $data['deadline_warning_days']);

        return back()->with('success', 'Settings saved.');
    }

    private function canOpen(User $user): bool
    {
        return $user->hasPermission('users.manage')
            || $user->hasPermission('users.view')
            || $user->hasPermission('brands.manage')
            || $user->hasPermission('platforms.manage')
            || $user->hasPermission('workflow.manage')
            || $user->hasPermission('taxonomy.manage')
            || $user->hasPermission('tags.manage')
            || $user->hasPermission('settings.manage');
    }

    /**
     * @return array{name: string, description: ?string, brand_id: ?int}
     */
    private function segmentData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
        ]);

        if (! empty($data['brand_id'])) {
            abort_unless($request->user()->canAccessBrand((int) $data['brand_id']), 403);
        }

        $data['brand_id'] = $data['brand_id'] ?? null;
        $data['description'] = ($data['description'] ?? '') === '' ? null : $data['description'];

        return $data;
    }

    /**
     * @return array{name: string, slug: string, brand_id: ?int}
     */
    private function tagData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
        ]);

        if (! empty($data['brand_id'])) {
            abort_unless($request->user()->canAccessBrand((int) $data['brand_id']), 403);
        }

        $slug = Str::slug($data['name']) ?: 'tag';
        $exists = Tag::query()
            ->where('slug', $slug)
            ->where('brand_id', $data['brand_id'] ?? null)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'That tag already exists for this brand.']);
        }

        return [
            'name' => $data['name'],
            'slug' => $slug,
            'brand_id' => $data['brand_id'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function brandData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:2048', new HttpUrl],
            'industry' => ['nullable', 'string', 'max:120'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
        ]);

        foreach (['description', 'website', 'industry', 'primary_color'] as $field) {
            if (($data[$field] ?? null) === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * @param  class-string  $model
     */
    private function uniqueSlug(string $model, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while (
            $model::query()
                ->when(
                    method_exists($model, 'bootSoftDeletes'),
                    fn ($query) => $query->withTrashed(),
                )
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  list<int|string>  $socialAccountIds
     * @param  list<int|string>  $brandIds
     * @return list<int>
     */
    private function validatedSocialAccountIds(User $actor, array $socialAccountIds, array $brandIds): array
    {
        $requested = array_values(array_unique(array_map('intval', $socialAccountIds)));
        if ($requested === []) {
            return [];
        }

        $brandIds = array_values(array_unique(array_map('intval', $brandIds)));
        $allowedBrandIds = $actor->isAdmin()
            ? $brandIds
            : array_values(array_intersect($brandIds, $actor->accessibleBrandIds()));

        if ($allowedBrandIds === []) {
            throw ValidationException::withMessages([
                'social_account_ids' => 'Assign at least one brand before granting Facebook page access.',
            ]);
        }

        $validIds = SocialAccount::query()
            ->whereHas('platform', fn ($query) => $query->where('slug', 'facebook'))
            ->whereIn('brand_id', $allowedBrandIds)
            ->whereIn('id', $requested)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validIds) !== count($requested)) {
            throw ValidationException::withMessages([
                'social_account_ids' => 'One or more Facebook pages are invalid for the selected brands.',
            ]);
        }

        return $validIds;
    }
}
