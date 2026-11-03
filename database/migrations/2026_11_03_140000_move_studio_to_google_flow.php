<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        // Images now come from Google Flow, so the API model and quality no longer apply.
        Schema::table('studio_generations', function (Blueprint $table) {
            $table->dropColumn(['image_size', 'model']);
        });

        // Studio no longer uses a Gemini API key: do not leave a saved one behind.
        Storage::disk('local')->delete('gemini-credentials.json');
    }

    public function down(): void
    {
        Schema::table('studio_generations', function (Blueprint $table) {
            $table->string('image_size', 4)->nullable();
            $table->string('model', 60)->nullable();
        });
    }
};
