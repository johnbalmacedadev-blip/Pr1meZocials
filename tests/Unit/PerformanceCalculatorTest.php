<?php

namespace Tests\Unit;

use App\Services\PerformanceCalculator;
use PHPUnit\Framework\TestCase;

class PerformanceCalculatorTest extends TestCase
{
    public function test_rates_use_the_documented_denominators(): void
    {
        $metrics = (new PerformanceCalculator)->calculate([
            'impressions' => 1000,
            'reach' => 10,
            'likes' => 40,
            'comments_count' => 10,
            'shares' => 5,
            'saves' => 5,
            'link_clicks' => 50,
            'conversions' => 10,
            'leads' => 4,
            'ad_spend' => 80,
            'revenue' => 200,
            'video_views_3s' => 250,
        ]);

        $this->assertSame(6.0, $metrics['engagement_rate']);
        $this->assertSame(5.0, $metrics['click_through_rate']);
        $this->assertSame(20.0, $metrics['conversion_rate']);
        $this->assertSame(20.0, $metrics['cost_per_lead']);
        $this->assertSame(2.5, $metrics['return_on_ad_spend']);
        $this->assertSame(25.0, $metrics['three_second_view_rate']);
    }

    public function test_missing_inputs_leave_the_rate_blank(): void
    {
        $metrics = (new PerformanceCalculator)->calculate([
            'reach' => 500,
            'likes' => 20,
        ]);

        $this->assertNull($metrics['engagement_rate']);
        $this->assertNull($metrics['click_through_rate']);
        $this->assertNull($metrics['cost_per_lead']);
    }
}
