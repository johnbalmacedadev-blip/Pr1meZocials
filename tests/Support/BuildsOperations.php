<?php

namespace Tests\Support;

use App\Models\Brand;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TaxonomySeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Support\Str;

trait BuildsOperations
{
    protected function seedOperations(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            WorkflowSeeder::class,
            PlatformSeeder::class,
            TaxonomySeeder::class,
        ]);
    }

    protected function makeBrand(string $name = 'Northwind'): Brand
    {
        return Brand::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'is_active' => true,
        ]);
    }

    protected function makeUser(string $role, ?Brand $brand = null, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        if ($brand) {
            $user->brands()->attach($brand);
        }

        return $user;
    }
}
