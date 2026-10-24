<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'facebook_user_token')) {
                $table->text('facebook_user_token')->nullable()->after('remember_token');
            }
            if (! Schema::hasColumn('users', 'facebook_token_expires_at')) {
                $table->timestamp('facebook_token_expires_at')->nullable()->after('facebook_user_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['facebook_user_token', 'facebook_token_expires_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
