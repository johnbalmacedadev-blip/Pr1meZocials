<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASED = ['picked_by' => null, 'picked_at' => null, 'draw_token' => null, 'held_until' => null, 'picker_slip_id' => null];

    /**
     * Kyle was listed twice but is one person. Picks already made are kept wherever they still make sense.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $kyles = DB::table('rollet_slips')->where('name', 'Kyle')->orderBy('id')->get();
            if ($kyles->count() < 2) {
                return;
            }

            // Remove a Kyle nobody has drawn if there is one, so that result survives.
            $remove = $kyles->whereNull('picked_at')->last() ?? $kyles->last();
            $keepId = $kyles->firstWhere('id', '!=', $remove->id)->id;

            $table = fn () => DB::table('rollet_slips');
            $confirmed = fn (int $pickerId) => $table()->where('picker_slip_id', $pickerId)->whereNotNull('picked_at');

            if ($confirmed($keepId)->exists()) {
                // Both Kyles picked: Kyle is one person, so the removed one's pick goes back in the draw.
                $table()->where('picker_slip_id', $remove->id)->whereNotNull('picked_at')->update(self::RELEASED);
            } else {
                // Only one Kyle picked: that pick is now Kyle's.
                $table()->where('picker_slip_id', $remove->id)->whereNotNull('picked_at')->update(['picker_slip_id' => $keepId]);
            }

            // Anything still being confirmed by the removed Kyle starts again.
            $table()->where('picker_slip_id', $remove->id)->update(self::RELEASED);
            $table()->where('id', $remove->id)->delete();
            $table()->where('picker_slip_id', $keepId)->whereNotNull('picked_at')->update(['picked_by' => 'Kyle']);

            // One Kyle drew the other, which would now mean Kyle has his own name.
            $table()->where('id', $keepId)->where('picker_slip_id', $keepId)->update(self::RELEASED);
        });
    }

    public function down(): void
    {
        // Kyle stays as one person.
    }
};
