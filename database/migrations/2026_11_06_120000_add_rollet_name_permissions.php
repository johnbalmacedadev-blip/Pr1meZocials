<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The catalog seeder only adds a page's permissions to roles that have none for it,
     * and these roles already have Rollet Results, so the new actions are granted here.
     */
    public function up(): void
    {
        $ids = collect(['create' => 'Add Rollet Results', 'update' => 'Edit Rollet Results'])
            ->map(fn (string $name, string $action) => Permission::query()->updateOrCreate(
                ['slug' => 'rollet.'.$action],
                ['name' => $name, 'group' => 'Pages'],
            )->id)
            ->values();

        Role::query()
            ->whereIn('slug', ['administrator', 'marketing_manager'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }

    public function down(): void
    {
        Permission::query()->whereIn('slug', ['rollet.create', 'rollet.update'])->delete();
    }
};
