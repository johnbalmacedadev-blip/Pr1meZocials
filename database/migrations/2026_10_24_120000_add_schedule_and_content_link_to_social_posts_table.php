<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('social_posts', 'content_item_id')) {
                $table->foreignId('content_item_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('social_posts', 'scheduled_publish_at')) {
                $table->timestamp('scheduled_publish_at')->nullable()->after('published_at')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            if (Schema::hasColumn('social_posts', 'content_item_id')) {
                $table->dropConstrainedForeignId('content_item_id');
            }
            if (Schema::hasColumn('social_posts', 'scheduled_publish_at')) {
                $table->dropColumn('scheduled_publish_at');
            }
        });
    }
};
