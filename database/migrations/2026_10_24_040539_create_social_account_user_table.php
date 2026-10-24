<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_account_user', function (Blueprint $table) {
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['social_account_id', 'user_id']);
        });

        // Preserve current posting ability: non-admin publishers keep pages under their brands.
        if (! Schema::hasTable('social_accounts') || ! Schema::hasTable('brand_user') || ! Schema::hasTable('role_user')) {
            return;
        }

        $publisherIds = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permissions.slug', 'content.publish')
            ->whereNull('users.deleted_at')
            ->distinct()
            ->pluck('users.id');

        $adminIds = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.slug', 'administrator')
            ->whereNull('users.deleted_at')
            ->pluck('users.id')
            ->all();

        foreach ($publisherIds as $userId) {
            if (in_array((int) $userId, array_map('intval', $adminIds), true)) {
                continue;
            }

            $brandIds = DB::table('brand_user')->where('user_id', $userId)->pluck('brand_id');
            if ($brandIds->isEmpty()) {
                continue;
            }

            $pageIds = DB::table('social_accounts')->whereIn('brand_id', $brandIds)->pluck('id');
            foreach ($pageIds as $pageId) {
                DB::table('social_account_user')->insertOrIgnore([
                    'social_account_id' => $pageId,
                    'user_id' => $userId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_account_user');
    }
};
