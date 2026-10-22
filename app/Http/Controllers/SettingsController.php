<?php

namespace App\Http\Controllers;

use App\Models\AudienceSegment;
use App\Models\Brand;
use App\Models\ContentObjective;
use App\Models\ContentPillar;
use App\Models\Platform;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tag;
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
                    ->with(['roles:id,name,slug', 'brands:id,name'])
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
                ])
                : [],
            'roles' => Role::query()->orderBy('name')->get(['id', 'name', 'slug', 'description']),
            'brands' => Brand::query()
                ->when(! $user->isAdmin(), fn ($query) => $query->whereIn('id', $user->accessibleBrandIds() ?: [0]))
                ->orderBy('name')
                ->get(),
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
        ]);

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
        ]);

        $adminRoleId = Role::query()->where('slug', 'administrator')->value('id');
        $keepsAdmin = in_array($adminRoleId, array_map('intval', $data['role_ids']), true) && $data['is_active'];
        $activeAdmins = User::query()->where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', 'administrator'))->count();

        if ($user->isAdmin() && $user->is_active && ! $keepsAdmin && $activeAdmins <= 1) {
            throw ValidationException::withMessages([
                'role_ids' => 'At least one active administrator is required.',
            ]);
        }

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
        $this->authorize('update', $brand);
        $brand->update($this->brandData($request));

        return back()->with('success', 'Brand updated.');
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

    public function storeObjective(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('taxonomy.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:500']]);
        ContentObjective::query()->create([...$data, 'slug' => $this->uniqueSlug(ContentObjective::class, $data['name'])]);

        return back()->with('success', 'Objective added.');
    }

    public function storeSegment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('taxonomy.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
        ]);

        if (! empty($data['brand_id'])) {
            abort_unless($request->user()->canAccessBrand((int) $data['brand_id']), 403);
        }

        AudienceSegment::query()->create($data);

        return back()->with('success', 'Audience segment added.');
    }

    public function storeTag(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('tags.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
        ]);

        if (! empty($data['brand_id'])) {
            abort_unless($request->user()->canAccessBrand((int) $data['brand_id']), 403);
        }

        $slug = Str::slug($data['name']) ?: 'tag';
        $exists = Tag::query()->where('slug', $slug)->where('brand_id', $data['brand_id'] ?? null)->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'That tag already exists for this brand.']);
        }

        Tag::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'brand_id' => $data['brand_id'] ?? null,
        ]);

        return back()->with('success', 'Tag added.');
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
    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $suffix = 2;
        $query = method_exists($model, 'bootSoftDeletes') ? $model::withTrashed() : $model::query();

        while ((clone $query)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
            $query = method_exists($model, 'bootSoftDeletes') ? $model::withTrashed() : $model::query();
        }

        return $slug;
    }
}
