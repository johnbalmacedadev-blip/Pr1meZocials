<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_resource_link', function (Blueprint $table) {
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_link_id')->constrained()->cascadeOnDelete();
            $table->primary(['content_item_id', 'resource_link_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_resource_link');
    }
};
