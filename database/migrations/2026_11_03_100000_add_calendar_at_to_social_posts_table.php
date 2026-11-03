<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            // Where the team wants a published post shown on calendars. published_at stays the real time.
            $table->timestamp('calendar_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn('calendar_at');
        });
    }
};
