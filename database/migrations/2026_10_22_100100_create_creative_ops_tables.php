<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->string('industry')->nullable();
            $table->string('primary_color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('brand_user', function (Blueprint $table) {
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['brand_id', 'user_id']);
        });

        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 20)->default('zinc');
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('handle')->nullable();
            $table->string('profile_url', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('objective')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('planning');
            $table->decimal('budget', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['brand_id', 'slug']);
        });

        Schema::create('campaign_user', function (Blueprint $table) {
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['campaign_id', 'user_id']);
        });

        Schema::create('workflow_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 20)->default('zinc');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(true);
            $table->string('enter_permission')->nullable();
            $table->json('allowed_transitions')->nullable();
            $table->json('required_fields')->nullable();
            $table->timestamps();
        });

        Schema::create('content_pillars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('content_objectives', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('audience_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->index(['brand_id', 'slug']);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('taggable_type', 100);
            $table->unsignedBigInteger('taggable_id');
            $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
            $table->index(['taggable_type', 'taggable_id']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('status_id')->constrained('workflow_statuses')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('pillar_id')->nullable()->constrained('content_pillars')->nullOnDelete();
            $table->foreignId('objective_id')->nullable()->constrained('content_objectives')->nullOnDelete();
            $table->foreignId('audience_segment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audience_notes')->nullable();
            $table->string('funnel_stage', 32)->nullable();
            $table->string('priority', 16)->default('medium');
            $table->string('distribution', 16)->default('organic');
            $table->string('format', 32)->default('other');
            $table->unsignedTinyInteger('complexity')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('copywriter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('video_editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('target_production_date')->nullable();
            $table->date('deadline')->nullable();
            $table->dateTime('scheduled_publish_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->text('hypothesis')->nullable();
            $table->text('success_metric')->nullable();
            $table->text('original_idea')->nullable();
            $table->string('hook')->nullable();
            $table->text('main_message')->nullable();
            $table->text('content_angle')->nullable();
            $table->text('creative_concept')->nullable();
            $table->text('storyline')->nullable();
            $table->text('caption_draft')->nullable();
            $table->string('call_to_action')->nullable();
            $table->text('desired_outcome')->nullable();
            $table->text('key_talking_points')->nullable();
            $table->text('visual_direction')->nullable();
            $table->text('required_assets')->nullable();
            $table->text('brand_guidelines_notes')->nullable();
            $table->text('production_instructions')->nullable();
            $table->string('inspiration_source')->nullable();
            $table->string('inspiration_url', 2048)->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand_id', 'status_id']);
            $table->index('owner_id');
            $table->index('deadline');
            $table->index('priority');
            $table->index('format');
            $table->index('published_at');
        });

        Schema::create('content_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['content_item_id', 'user_id']);
        });

        Schema::create('creative_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('original_filename');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type');
            $table->string('extension', 16);
            $table->unsignedBigInteger('size');
            $table->string('kind', 32)->default('other');
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('parent_asset_id')->nullable()->constrained('creative_assets')->nullOnDelete();
            $table->boolean('is_final')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['content_item_id', 'kind']);
        });

        Schema::create('content_platform_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->restrictOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('creative_asset_id')->nullable()->constrained('creative_assets')->nullOnDelete();
            $table->string('format', 32);
            $table->string('headline')->nullable();
            $table->text('caption')->nullable();
            $table->string('hashtags', 500)->nullable();
            $table->string('call_to_action')->nullable();
            $table->text('instructions')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('publishing_status', 32)->default('draft');
            $table->string('published_url', 2048)->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->index('scheduled_at');
            $table->index(['content_item_id', 'platform_id']);
            $table->index('publishing_status');
        });

        Schema::table('creative_assets', function (Blueprint $table) {
            $table->foreignId('platform_version_id')
                ->nullable()
                ->after('content_item_id')
                ->constrained('content_platform_versions')
                ->nullOnDelete();
        });

        Schema::create('content_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('content_comments')->cascadeOnDelete();
            $table->text('body');
            $table->json('mention_ids')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('content_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->string('description');
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index(['content_item_id', 'created_at']);
        });

        Schema::create('content_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creative_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 32);
            $table->text('notes')->nullable();
            $table->unsignedInteger('submission_number')->default(1);
            $table->timestamps();
        });

        Schema::create('publishing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_platform_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('published_url', 2048)->nullable();
            $table->timestamp('published_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_platform_version_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('source', 32)->default('manual');
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('impressions')->nullable();
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('video_views_3s')->nullable();
            $table->unsignedBigInteger('watch_time_seconds')->nullable();
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments_count')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->unsignedBigInteger('saves')->nullable();
            $table->unsignedBigInteger('link_clicks')->nullable();
            $table->unsignedBigInteger('leads')->nullable();
            $table->unsignedBigInteger('conversions')->nullable();
            $table->decimal('revenue', 12, 2)->nullable();
            $table->decimal('ad_spend', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['period_start', 'period_end']);
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('dedupe_key');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'dedupe_key']);
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('module', 64);
            $table->json('filters');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('performance_reports');
        Schema::dropIfExists('publishing_records');
        Schema::dropIfExists('content_approvals');
        Schema::dropIfExists('content_activities');
        Schema::dropIfExists('content_comments');
        Schema::table('creative_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('platform_version_id');
        });
        Schema::dropIfExists('content_platform_versions');
        Schema::dropIfExists('creative_assets');
        Schema::dropIfExists('content_collaborators');
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('audience_segments');
        Schema::dropIfExists('content_objectives');
        Schema::dropIfExists('content_pillars');
        Schema::dropIfExists('workflow_statuses');
        Schema::dropIfExists('campaign_user');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('platforms');
        Schema::dropIfExists('brand_user');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
