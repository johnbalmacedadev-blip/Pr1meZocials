<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rollet_slips', function (Blueprint $table) {
            // The slip of the person who drew this one, so nobody draws themselves or picks twice.
            $table->unsignedBigInteger('picker_slip_id')->nullable()->index();
        });

        // Picks made when people typed their name: link them to the matching name where one exists.
        $slips = DB::table('rollet_slips')->orderBy('id')->get(['id', 'name']);
        $used = [];
        foreach (DB::table('rollet_slips')->whereNotNull('picked_at')->orderBy('picked_at')->get(['id', 'picked_by']) as $pick) {
            $match = $slips->first(fn ($slip) => mb_strtolower($slip->name) === mb_strtolower(trim((string) $pick->picked_by)) && ! in_array($slip->id, $used, true));
            if ($match) {
                $used[] = $match->id;
                DB::table('rollet_slips')->where('id', $pick->id)->update(['picker_slip_id' => $match->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('rollet_slips', function (Blueprint $table) {
            $table->dropIndex(['picker_slip_id']);
            $table->dropColumn('picker_slip_id');
        });
    }
};
