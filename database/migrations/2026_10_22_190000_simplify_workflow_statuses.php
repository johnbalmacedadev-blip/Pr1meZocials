<?php

use Database\Seeders\WorkflowSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new WorkflowSeeder)->run();
    }

    public function down(): void
    {
        // The previous stage list is not restored. Existing cards stay on the simplified stages.
    }
};
