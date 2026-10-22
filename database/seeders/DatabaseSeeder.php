<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            WorkflowSeeder::class,
            PlatformSeeder::class,
            TaxonomySeeder::class,
        ]);

        if (filter_var(env('SEED_DEMO', true), FILTER_VALIDATE_BOOL)) {
            $this->call(DemoSeeder::class);
        }
    }
}
