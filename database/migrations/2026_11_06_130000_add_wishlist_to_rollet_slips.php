<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rollet_slips', function (Blueprint $table) {
            $table->text('wishlist')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rollet_slips', function (Blueprint $table) {
            $table->dropColumn('wishlist');
        });
    }
};
