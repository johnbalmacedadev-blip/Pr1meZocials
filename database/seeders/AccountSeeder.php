<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'slug');

        $prime = Brand::query()->updateOrCreate(['slug' => 'prime-z'], [
            'name' => 'Prime Z',
            'description' => 'Prime Z creative operations.',
            'primary_color' => '#284CB8',
            'is_active' => true,
        ]);

        $admin = User::query()->updateOrCreate(['username' => 'admin'], [
            'name' => 'Admin',
            'email' => 'admin@primez.test',
            'password' => 'admin',
            'job_title' => 'Main administrator',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->roles()->sync([$roles['administrator']]);

        $user = User::query()->updateOrCreate(['username' => '1'], [
            'name' => 'User 1',
            'email' => 'user1@primez.test',
            'password' => 'oassword',
            'job_title' => 'User',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->roles()->sync([$roles['social_media_manager']]);
        $user->brands()->sync(Brand::query()->pluck('id')->all());

        User::query()->each(function (User $person) use ($prime): void {
            $person->brands()->syncWithoutDetaching([$prime->id]);
        });
    }
};
