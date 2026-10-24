<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('handle');
            $table->text('access_token')->nullable()->after('external_id');
            $table->timestamp('token_expires_at')->nullable()->after('access_token');
            $table->json('meta')->nullable()->after('token_expires_at');
        });

        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('caption')->nullable();
            $table->string('media_type'); // image|video
            $table->string('media_path')->nullable();
            $table->string('media_name')->nullable();
            $table->string('facebook_post_id')->nullable()->index();
            $table->string('permalink_url', 2048)->nullable();
            $table->string('status')->default('draft'); // draft|publishing|published|failed
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('comments_count')->default(0);
            $table->unsignedBigInteger('shares_count')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->decimal('engagement_rate', 8, 2)->nullable();
            $table->timestamp('metrics_synced_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'access_token', 'token_expires_at', 'meta']);
        });
    }
};
