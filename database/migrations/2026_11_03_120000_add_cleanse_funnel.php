<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'pr1me-cleanse';

    public function up(): void
    {
        $brandId = DB::table('brands')->where('slug', 'prime-z')->value('id')
            ?? DB::table('brands')->orderBy('id')->value('id');

        if (! $brandId || DB::table('funnels')->where('slug', self::SLUG)->exists()) {
            return;
        }

        // Starts as a private draft at no price: the edit form needs real prices before it can go live.
        DB::table('funnels')->insert([
            'brand_id' => $brandId,
            'name' => 'Pr1me Cleanse',
            'product_name' => 'Pr1me Cleanse Psyllium Barley + Fiber',
            'slug' => self::SLUG,
            'template' => 'cleanse',
            'status' => 'draft',
            'unit_price' => 0,
            'compare_at_price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep the funnel if customers have ordered from it.
        DB::table('funnels')
            ->where('slug', self::SLUG)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('funnel_orders')->whereColumn('funnel_orders.funnel_id', 'funnels.id'))
            ->delete();
    }
};
