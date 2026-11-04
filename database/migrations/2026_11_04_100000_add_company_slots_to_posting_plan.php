<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    private const SLOTS = ['company_am', 'company_pm', 'company_night'];

    public function up(): void
    {
        $rows = [];
        foreach (self::WEEKDAYS as $weekday) {
            foreach (self::SLOTS as $slot) {
                $rows[] = [
                    'weekday' => $weekday,
                    'slot' => $slot,
                    'format' => 'reel_poster',
                    'title' => 'Company Post',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('posting_plan_slots')->insert($rows);
    }

    public function down(): void
    {
        DB::table('posting_plan_slots')->whereIn('slot', self::SLOTS)->delete();
    }
};
