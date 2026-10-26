<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_posts')) {
            return;
        }

        Schema::table('social_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('social_posts', 'tiktok_publish_id')) {
                $table->string('tiktok_publish_id')->nullable()->after('facebook_post_id')->index();
            }
            if (! Schema::hasColumn('social_posts', 'cross_post_of_id')) {
                $table->foreignId('cross_post_of_id')
                    ->nullable()
                    ->after('tiktok_publish_id')
                    ->constrained('social_posts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('social_posts')) {
            return;
        }

        Schema::table('social_posts', function (Blueprint $table) {
            if (Schema::hasColumn('social_posts', 'cross_post_of_id')) {
                $table->dropConstrainedForeignId('cross_post_of_id');
            }
            if (Schema::hasColumn('social_posts', 'tiktok_publish_id')) {
                $table->dropColumn('tiktok_publish_id');
            }
        });
    }
};
