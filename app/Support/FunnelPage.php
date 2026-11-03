<?php

namespace App\Support;

use App\Models\Funnel;

/**
 * Sales page templates for funnels. Each template is a static HTML file in
 * resources/funnels with its images and videos in public/funnel-assets/<template>.
 */
class FunnelPage
{
    /**
     * @return array<string, string> template key => label
     */
    public static function templates(): array
    {
        return [
            'ginger-turmeric' => 'Ginger Turmeric sales page',
            'coffee-20in1' => '20in1 Coffee sales page',
            'soap-4in1' => 'Pr1me Skin Soap sales page',
            'cleanse' => 'Pr1me Cleanse sales page',
        ];
    }

    public static function render(Funnel $funnel): string
    {
        $html = file_get_contents(resource_path('funnels/'.$funnel->template.'.html'));

        return strtr($html, [
            '{{assets}}' => e(asset('funnel-assets/'.$funnel->template)),
            '{{runtime}}' => e(asset('funnel-assets/funnel.js').'?v='.@filemtime(public_path('funnel-assets/funnel.js'))),
            '{{page_url}}' => e($funnel->publicUrl()),
            '{{order_url}}' => e(route('funnels.public.order', $funnel->slug)),
            '{{price}}' => self::money($funnel->unit_price),
            '{{compare_price}}' => '₱'.number_format((float) $funnel->compare_at_price, 2),
            '{{discount}}' => (string) self::discountPercent($funnel),
        ]);
    }

    /**
     * ₱890 for whole amounts, ₱1,112.50 otherwise.
     */
    public static function money(string|float $amount): string
    {
        $value = (float) $amount;

        return '₱'.number_format($value, floor($value) === $value ? 0 : 2);
    }

    public static function discountPercent(Funnel $funnel): int
    {
        $compare = (float) $funnel->compare_at_price;

        return $compare > 0 ? (int) round((1 - (float) $funnel->unit_price / $compare) * 100) : 0;
    }
}
