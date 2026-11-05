<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollet_slips', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('picked_by', 80)->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->string('draw_token', 64)->nullable()->unique();
            $table->timestamp('held_until')->nullable();
            $table->timestamps();
        });

        // Kyle is on the list twice, so there are two Kyle slips.
        $names = ['Sam', 'Mami', 'Daddy', 'Neng', 'Jerom', 'Alec', 'Kt', 'Acxel', 'Maurien', 'Kyle', 'Liam', 'Russell', 'Glen', 'Vince', 'Jam', 'Beloved john', 'Kyle'];

        DB::table('rollet_slips')->insert(array_map(fn (string $name) => [
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ], $names));
    }

    public function down(): void
    {
        Schema::dropIfExists('rollet_slips');
    }
};
