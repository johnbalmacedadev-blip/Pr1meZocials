<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('recurrence', 32)->default('none');
            $table->json('recurrence_weekdays')->nullable();
            $table->string('default_poster_path')->nullable();
            $table->string('default_poster_name')->nullable();
            $table->string('default_video_path')->nullable();
            $table->string('default_video_name')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand_id', 'starts_on']);
            $table->index(['status', 'starts_on']);
        });

        Schema::create('event_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('occurs_on');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('title_override')->nullable();
            $table->text('notes')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('poster_name')->nullable();
            $table->string('video_path')->nullable();
            $table->string('video_name')->nullable();
            $table->string('status', 32)->default('scheduled');
            $table->timestamps();

            $table->unique(['event_id', 'occurs_on']);
            $table->index(['occurs_on', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_occurrences');
        Schema::dropIfExists('events');
    }
};
