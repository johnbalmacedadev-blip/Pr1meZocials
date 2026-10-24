<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_posts') || Schema::hasColumn('social_posts', 'media_files')) {
            return;
        }

        Schema::table('social_posts', function (Blueprint $table) {
            $table->json('media_files')->nullable()->after('media_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('social_posts') || ! Schema::hasColumn('social_posts', 'media_files')) {
            return;
        }

        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn('media_files');
        });
    }
};
