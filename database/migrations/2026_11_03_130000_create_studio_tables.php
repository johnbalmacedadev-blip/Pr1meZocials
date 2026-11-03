<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 16);
            $table->string('name', 150);
            $table->string('path');
            $table->string('mime_type', 32);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index(['brand_id', 'kind']);
        });

        Schema::create('studio_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('studio_generations')->nullOnDelete();
            $table->text('prompt');
            $table->string('aspect_ratio', 8);
            $table->string('image_size', 4);
            $table->string('model', 60);
            $table->json('reference_keys')->nullable();
            $table->string('path');
            $table->string('mime_type', 32);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_generations');
        Schema::dropIfExists('studio_references');
    }
};
