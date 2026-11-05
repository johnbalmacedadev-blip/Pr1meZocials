<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('rollet_slips')->where('name', 'Ichan')->exists()) {
            return;
        }

        DB::table('rollet_slips')->insert(['name' => 'Ichan', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Keep Ichan if someone has drawn the name or Ichan has already picked.
        $id = DB::table('rollet_slips')->where('name', 'Ichan')->whereNull('picked_at')->value('id');

        if ($id && ! DB::table('rollet_slips')->where('picker_slip_id', $id)->exists()) {
            DB::table('rollet_slips')->where('id', $id)->delete();
        }
    }
};
