<?php

namespace App\Services;

class PerformanceCalculator
{
    public const FORMULAS = [
        'engagement_rate' => 'Engagement rate = (likes + comments + shares + saves) ÷ impressions × 100. Reach is never used as the denominator. The rate is left blank when impressions are missing or zero, or when no interaction counts were recorded.',
        'click_through_rate' => 'Click-through rate = link clicks ÷ impressions × 100. Blank when impressions or link clicks were not recorded, or when impressions are zero.',
        'conversion_rate' => 'Conversion rate = conversions ÷ link clicks × 100. Link clicks are the denominator. Blank when link clicks are missing or zero.',
        'cost_per_lead' => 'Cost per lead = advertising spend ÷ leads. Blank when spend was not recorded or leads are zero.',
        'return_on_ad_spend' => 'Return on ad spend = attributable revenue ÷ advertising spend. Blank when spend is missing or zero.',
        'three_second_view_rate' => 'Three-second view rate = three-second video views ÷ impressions × 100. Blank when impressions are missing or zero.',
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, float|null>
     */
    public function calculate(array $input): array
    {
        $impressions = $this->intOrNull($input['impressions'] ?? null);
        $likes = $this->intOrNull($input['likes'] ?? null);
        $comments = $this->intOrNull($input['comments_count'] ?? $input['comments'] ?? null);
        $shares = $this->intOrNull($input['shares'] ?? null);
        $saves = $this->intOrNull($input['saves'] ?? null);
        $clicks = $this->intOrNull($input['link_clicks'] ?? null);
        $leads = $this->intOrNull($input['leads'] ?? null);
        $conversions = $this->intOrNull($input['conversions'] ?? null);
        $views3s = $this->intOrNull($input['video_views_3s'] ?? null);
        $spend = $this->floatOrNull($input['ad_spend'] ?? null);
        $revenue = $this->floatOrNull($input['revenue'] ?? null);

        $interactionsRecorded = $likes !== null || $comments !== null || $shares !== null || $saves !== null;
        $interactions = ($likes ?? 0) + ($comments ?? 0) + ($shares ?? 0) + ($saves ?? 0);

        return [
            'engagement_rate' => ($impressions !== null && $impressions > 0 && $interactionsRecorded)
                ? round($interactions / $impressions * 100, 2)
                : null,
            'click_through_rate' => ($impressions !== null && $impressions > 0 && $clicks !== null)
                ? round($clicks / $impressions * 100, 2)
                : null,
            'conversion_rate' => ($clicks !== null && $clicks > 0 && $conversions !== null)
                ? round($conversions / $clicks * 100, 2)
                : null,
            'cost_per_lead' => ($spend !== null && $leads !== null && $leads > 0)
                ? round($spend / $leads, 2)
                : null,
            'return_on_ad_spend' => ($spend !== null && $spend > 0 && $revenue !== null)
                ? round($revenue / $spend, 2)
                : null,
            'three_second_view_rate' => ($impressions !== null && $impressions > 0 && $views3s !== null)
                ? round($views3s / $impressions * 100, 2)
                : null,
        ];
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
