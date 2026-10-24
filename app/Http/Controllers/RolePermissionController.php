<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\PermissionPreset;
use App\Models\Role;
use App\Support\PageAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RolePermissionController extends Controller
{
    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->guardAdministratorRole($request, $role);

        $data = $this->validatedMatrix($request);
        $slugs = PageAccess::expand($data['matrix'], $data['extras']);
        $ids = Permission::query()->whereIn('slug', $slugs)->pluck('id');

        $role->permissions()->sync($ids);

        return back()->with('success', $role->name.' permissions updated.');
    }

    public function storePreset(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validatedMatrix($request);
        $name = trim((string) $request->input('name', ''));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Give this preset a name.']);
        }

        $base = Str::slug($name) ?: 'preset';
        $slug = $base;
        $suffix = 1;
        while (PermissionPreset::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        PermissionPreset::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $request->input('description'),
            'matrix' => $data['matrix'],
            'extras' => $data['extras'],
            'is_system' => false,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Preset saved.');
    }

    public function applyPreset(Request $request, Role $role, PermissionPreset $preset): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->guardAdministratorRole($request, $role);

        $matrix = is_array($preset->matrix) ? $preset->matrix : [];
        $extras = is_array($preset->extras) ? $preset->extras : [];
        $slugs = PageAccess::expand($matrix, $extras);
        $ids = Permission::query()->whereIn('slug', $slugs)->pluck('id');

        $role->permissions()->sync($ids);

        return back()->with('success', 'Applied “'.$preset->name.'” to '.$role->name.'.');
    }

    public function destroyPreset(Request $request, PermissionPreset $preset): RedirectResponse
    {
        $this->authorizeManage($request);
        abort_if($preset->is_system, 403);

        $preset->delete();

        return back()->with('success', 'Preset removed.');
    }

    private function authorizeManage(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin() || $user->hasPermission('users.manage') || $user->hasPermission('settings.manage'),
            403
        );
    }

    private function guardAdministratorRole(Request $request, Role $role): void
    {
        if ($role->slug === 'administrator' && ! $request->user()->isAdmin()) {
            abort(403);
        }
    }

    /**
     * @return array{matrix: array<string, list<string>>, extras: list<string>}
     */
    private function validatedMatrix(Request $request): array
    {
        $pageKeys = array_column(PageAccess::pages(), 'key');
        $actionKeys = ['view', 'create', 'update', 'delete'];
        $extraSlugs = array_column(PageAccess::extras(), 'slug');

        $data = $request->validate([
            'matrix' => ['required', 'array'],
            'matrix.*' => ['array'],
            'matrix.*.*' => ['string', Rule::in($actionKeys)],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['string', Rule::in($extraSlugs)],
            'name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $matrix = [];
        foreach (PageAccess::pages() as $page) {
            $selected = array_values(array_intersect(
                $page['actions'],
                array_map('strval', $data['matrix'][$page['key']] ?? [])
            ));
            $matrix[$page['key']] = $selected;
        }

        return [
            'matrix' => $matrix,
            'extras' => array_values(array_unique(array_map('strval', $data['extras'] ?? []))),
        ];
    }
}
