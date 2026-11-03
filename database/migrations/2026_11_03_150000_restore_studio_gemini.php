<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Studio generates through the Gemini API again. Images saved in between have neither value.
        Schema::table('studio_generations', function (Blueprint $table) {
            $table->string('image_size', 4)->nullable();
            $table->string('model', 60)->nullable();
        });

        $this->restorePermission();
    }

    public function down(): void
    {
        Schema::table('studio_generations', function (Blueprint $table) {
            $table->dropColumn(['image_size', 'model']);
        });
    }

    /**
     * Connecting Google needs this permission. The catalog seeder only adds a page's
     * permissions to roles that have none for it, so it would not bring this one back.
     */
    public function restorePermission(): void
    {
        $permission = Permission::query()->updateOrCreate(
            ['slug' => 'studio.update'],
            ['name' => 'Edit Studio', 'group' => 'Pages'],
        );

        Role::query()
            ->whereIn('slug', ['administrator', 'marketing_manager'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->id]));
    }
};
