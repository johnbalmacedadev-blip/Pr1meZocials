<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'pr1me-skin-soap';

    public function up(): void
    {
        $brandId = DB::table('brands')->where('slug', 'prime-z')->value('id')
            ?? DB::table('brands')->orderBy('id')->value('id');

        if (! $brandId || DB::table('funnels')->where('slug', self::SLUG)->exists()) {
            return;
        }

        DB::table('funnels')->insert([
            'brand_id' => $brandId,
            'name' => 'Pr1me Skin Soap',
            'product_name' => 'Pr1me Skin Ultimate 4 in 1 Soap',
            'slug' => self::SLUG,
            'template' => 'soap-4in1',
            'status' => 'active',
            'unit_price' => 320,
            'compare_at_price' => 400,
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
