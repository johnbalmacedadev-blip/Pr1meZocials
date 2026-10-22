<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posting_schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->string('weekday', 10);
            $table->string('slot', 16);
            $table->date('post_on');
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['weekday', 'slot', 'post_on', 'content_item_id'], 'posting_schedule_slot_unique');
            $table->index(['weekday', 'slot', 'post_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_schedule_entries');
    }
};
