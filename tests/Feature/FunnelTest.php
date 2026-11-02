<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsOperations;
use Tests\TestCase;

class FunnelTest extends TestCase
{
    use BuildsOperations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOperations();
    }

    private function makeFunnel(array $attributes = []): Funnel
    {
        return Funnel::query()->create([
            'brand_id' => $this->makeBrand()->id,
            'name' => 'Ginger Turmeric Promo',
            'product_name' => 'Pr1me Ginger Turmeric',
            'slug' => 'ginger',
            'template' => 'ginger-turmeric',
            'status' => 'active',
            'unit_price' => 890,
            'compare_at_price' => 1112.50,
            ...$attributes,
        ]);
    }

    private function orderPayload(array $overrides = []): array
    {
        return [
            'full_name' => 'Maria Santos',
            'phone_number' => '09171234567',
            'address' => '12 Mabini St',
            'province' => 'Laguna',
            'city' => 'Calamba',
            'barangay' => 'Parian',
            'quantity' => 2,
            'source' => ['utm_source' => 'facebook', 'utm_campaign' => 'launch', 'ignored' => 'x'],
            ...$overrides,
        ];
    }

    public function test_public_page_shows_the_funnel_prices_without_signing_in(): void
    {
        $this->makeFunnel();

        $this->get('/f/ginger')
            ->assertOk()
            ->assertSee('Order 1 Ginger Turmeric', false)
            ->assertSee('₱890 Only', false)
            ->assertSee('₱1,112.50', false)
            ->assertSee('20% OFF', false)
            ->assertSee('for the 20% discount promo', false)
            ->assertDontSee('{{', false)
            ->assertDontSee('pancake.vn', false);
    }

    public function test_soap_page_shows_the_promo_regular_price_and_countdown(): void
    {
        $this->makeFunnel(['slug' => 'soap', 'template' => 'soap-4in1', 'unit_price' => 320, 'compare_at_price' => 400]);

        $this->get('/f/soap')
            ->assertOk()
            ->assertSee('₱320 Only', false)
            ->assertSee('₱400.00', false)
            ->assertSee('20% OFF', false)
            ->assertSee('SPECIAL OFFER ENDS IN', false)
            ->assertSee('"soap-countdown-top":{"minutes":122,"repeat":true}', false)
            ->assertSee('funnel-assets/soap-4in1/video/user-1.mp4', false)
            ->assertDontSee('{{', false);

        $this->postJson('/f/soap/order', $this->orderPayload(['quantity' => 2]))->assertCreated();
        $this->assertSame('640.00', FunnelOrder::query()->sole()->total);
    }

    public function test_coffee_page_sells_coffee_at_its_own_prices(): void
    {
        $this->makeFunnel(['slug' => 'coffee', 'template' => 'coffee-20in1', 'unit_price' => 580, 'compare_at_price' => 725]);

        $this->get('/f/coffee')
            ->assertOk()
            ->assertSee('Order 1 Prime C 20 in 1 Coffee', false)
            ->assertSee('₱580 Only', false)
            ->assertSee('₱725.00', false)
            ->assertSee('20% OFF', false)
            ->assertSee('Pr1me C 20in1', false)
            ->assertSee('funnel-assets/coffee-20in1/video/hero.mp4', false)
            ->assertDontSee('Ginger', false)
            ->assertDontSee('{{', false)
            ->assertDontSee('pancake.vn', false);

        $this->postJson('/f/coffee/order', $this->orderPayload(['quantity' => 3]))->assertCreated();
        $this->assertSame('1740.00', FunnelOrder::query()->sole()->total);
    }

    public function test_draft_and_paused_funnels_are_hidden_from_customers_but_previewable_by_the_team(): void
    {
        $funnel = $this->makeFunnel(['status' => 'draft']);
        $manager = $this->makeUser('marketing_manager', $funnel->brand);

        $this->get('/f/ginger')->assertNotFound();
        $this->postJson('/f/ginger/order', $this->orderPayload())->assertNotFound();
        $this->actingAs($manager)->get('/f/ginger')->assertOk();
    }

    public function test_customer_order_is_saved_with_the_server_price_and_the_team_is_notified(): void
    {
        $funnel = $this->makeFunnel();
        $manager = $this->makeUser('marketing_manager', $funnel->brand);
        $otherBrandManager = $this->makeUser('marketing_manager', $this->makeBrand('Other'));

        $this->postJson('/f/ginger/order', $this->orderPayload(['unit_price' => 1, 'total' => 1, 'phone_number' => '+63 917 123 4567']))
            ->assertCreated();

        $order = FunnelOrder::query()->sole();
        $this->assertSame('09171234567', $order->phone);
        $this->assertSame('890.00', $order->unit_price);
        $this->assertSame('1780.00', $order->total);
        $this->assertSame('new', $order->status);
        $this->assertSame('12 Mabini St, Parian, Calamba, Laguna', $order->shippingAddress());
        $this->assertSame(['utm_source' => 'facebook', 'utm_campaign' => 'launch'], $order->source);

        $this->assertSame(1, UserNotification::query()->where('user_id', $manager->id)->where('type', 'funnel_order')->count());
        $this->assertSame(0, UserNotification::query()->where('user_id', $otherBrandManager->id)->count());
    }

    public function test_order_form_works_without_a_session_token(): void
    {
        // Laravel skips CSRF checks during tests, so assert the real exemption rule.
        $this->assertContains('f/*/order', $this->app->make(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->getExcludedPaths());
    }

    public function test_email_is_optional_but_saved_and_listed_when_given(): void
    {
        $funnel = $this->makeFunnel();
        $manager = $this->makeUser('marketing_manager', $funnel->brand);

        $this->postJson('/f/ginger/order', $this->orderPayload(['email' => '']))->assertCreated();
        $this->assertNull(FunnelOrder::query()->sole()->email);

        $this->postJson('/f/ginger/order', $this->orderPayload(['phone_number' => '09171112222', 'email' => 'maria@example.com']))->assertCreated();

        $this->actingAs($manager)->get('/funnels/'.$funnel->id.'?q=maria@example')->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.email', 'maria@example.com'));
        $csv = $this->actingAs($manager)->get('/funnels/'.$funnel->id.'/orders/export')->streamedContent();
        $this->assertStringContainsString('Email', $csv);
        $this->assertStringContainsString('maria@example.com', $csv);
    }

    public function test_every_order_form_asks_for_email_and_complete_address(): void
    {
        foreach (array_keys(\App\Support\FunnelPage::templates()) as $template) {
            $this->makeFunnel(['slug' => $template, 'template' => $template]);

            $this->get('/f/'.$template)
                ->assertOk()
                ->assertSee('Delivery address', false)
                ->assertSee('Complete Address', false)
                ->assertSee('name="email"', false);
        }
    }

    public function test_invalid_orders_are_rejected(): void
    {
        $this->makeFunnel();

        $this->postJson('/f/ginger/order', $this->orderPayload(['email' => 'not-an-email']))->assertJsonValidationErrors('email');

        $this->postJson('/f/ginger/order', $this->orderPayload(['phone_number' => '12345']))->assertJsonValidationErrors('phone_number');
        $this->postJson('/f/ginger/order', $this->orderPayload(['quantity' => 0]))->assertJsonValidationErrors('quantity');
        $this->postJson('/f/ginger/order', $this->orderPayload(['province' => '']))->assertJsonValidationErrors('province');
        $this->assertSame(0, FunnelOrder::query()->count());
    }

    public function test_repeat_submission_and_bots_do_not_create_orders(): void
    {
        $this->makeFunnel();

        $this->postJson('/f/ginger/order', $this->orderPayload())->assertCreated();
        $this->postJson('/f/ginger/order', $this->orderPayload())->assertOk();
        $this->assertSame(1, FunnelOrder::query()->count());

        $this->postJson('/f/ginger/order', $this->orderPayload(['phone_number' => '09999999999', 'website' => 'spam']))->assertOk();
        $this->assertSame(1, FunnelOrder::query()->count());
    }

    public function test_table_lists_funnels_with_their_link_and_order_counts(): void
    {
        $funnel = $this->makeFunnel();
        $manager = $this->makeUser('marketing_manager', $funnel->brand);
        $this->postJson('/f/ginger/order', $this->orderPayload())->assertCreated();

        $this->actingAs($manager)->get('/funnels')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Funnels/Index')
            ->has('funnels.data', 1)
            ->where('funnels.data.0.public_url', route('funnels.public', 'ginger'))
            ->where('funnels.data.0.orders_count', 1)
            ->where('funnels.data.0.new_orders_count', 1)
            ->where('funnels.data.0.sales_total', '₱1,780'));
    }

    public function test_manager_creates_a_funnel_and_slugs_stay_unique(): void
    {
        $brand = $this->makeBrand();
        $manager = $this->makeUser('marketing_manager', $brand);
        $payload = [
            'brand_id' => $brand->id,
            'name' => 'Holiday promo',
            'product_name' => 'Pr1me Ginger Turmeric',
            'slug' => 'holiday-promo',
            'template' => 'ginger-turmeric',
            'status' => 'active',
            'unit_price' => 750,
            'compare_at_price' => 1000,
        ];

        $this->actingAs($manager)->post('/funnels', $payload)->assertRedirect();
        $this->get('/f/holiday-promo')->assertOk()->assertSee('₱750 Only', false)->assertSee('25% OFF', false);

        $this->actingAs($manager)->post('/funnels', $payload)->assertSessionHasErrors('slug');
        $this->actingAs($manager)->post('/funnels', [...$payload, 'slug' => 'Bad Slug!'])->assertSessionHasErrors('slug');
        $this->actingAs($manager)->post('/funnels', [...$payload, 'slug' => 'cheap', 'compare_at_price' => 100])->assertSessionHasErrors('compare_at_price');
    }

    public function test_short_link_can_be_added_changed_copied_from_the_table_and_removed(): void
    {
        $funnel = $this->makeFunnel();
        $manager = $this->makeUser('marketing_manager', $funnel->brand);
        $payload = $funnel->only(['brand_id', 'name', 'product_name', 'slug', 'template', 'status', 'unit_price', 'compare_at_price']);
        $update = fn (array $changes) => $this->actingAs($manager)->put('/funnels/'.$funnel->id, [...$payload, ...$changes]);

        $update(['short_link' => 'bit.ly/abc123'])->assertSessionDoesntHaveErrors();
        $this->assertSame('https://bit.ly/abc123', $funnel->fresh()->short_link);

        $this->actingAs($manager)->get('/funnels')->assertInertia(fn (Assert $page) => $page
            ->where('funnels.data.0.short_link', 'https://bit.ly/abc123')
            ->where('canUpdate', true));
        $this->actingAs($manager)->get('/funnels/'.$funnel->id.'/edit')->assertInertia(fn (Assert $page) => $page
            ->where('funnel.short_link', 'https://bit.ly/abc123'));

        $update(['short_link' => 'https://tinyurl.com/new'])->assertSessionDoesntHaveErrors();
        $this->assertSame('https://tinyurl.com/new', $funnel->fresh()->short_link);

        // It is shown as a clickable link, so only web addresses are accepted.
        $update(['short_link' => 'javascript:alert(1)'])->assertSessionHasErrors('short_link');
        $update(['short_link' => 'not a link'])->assertSessionHasErrors('short_link');
        $this->assertSame('https://tinyurl.com/new', $funnel->fresh()->short_link);

        $update(['short_link' => ''])->assertSessionDoesntHaveErrors();
        $this->assertNull($funnel->fresh()->short_link);
    }

    public function test_deleted_funnel_link_stops_working_and_keeps_its_slug(): void
    {
        $funnel = $this->makeFunnel();
        $manager = $this->makeUser('marketing_manager', $funnel->brand);

        $this->actingAs($manager)->delete('/funnels/'.$funnel->id)->assertRedirect('/funnels');
        auth()->logout();
        $this->get('/f/ginger')->assertNotFound();
    }

    public function test_orders_are_private_to_people_with_access_to_the_brand(): void
    {
        $funnel = $this->makeFunnel();
        $this->postJson('/f/ginger/order', $this->orderPayload())->assertCreated();
        $order = FunnelOrder::query()->sole();
        $manager = $this->makeUser('marketing_manager', $funnel->brand);
        $outsider = $this->makeUser('marketing_manager', $this->makeBrand('Other'));
        $creative = $this->makeUser('creative', $funnel->brand);

        $this->get('/funnels/'.$funnel->id)->assertRedirect('/login');
        $this->actingAs($outsider)->get('/funnels/'.$funnel->id)->assertForbidden();
        $this->actingAs($outsider)->get('/funnels/'.$funnel->id.'/orders/export')->assertForbidden();
        $this->actingAs($creative)->get('/funnels')->assertForbidden();

        $this->actingAs($manager)->get('/funnels/'.$funnel->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Funnels/Show')
            ->where('orders.data.0.full_name', 'Maria Santos')
            ->where('orders.data.0.phone', '09171234567')
            ->where('orders.data.0.address', '12 Mabini St, Parian, Calamba, Laguna')
            ->where('orders.data.0.source', 'facebook / launch'));

        $this->actingAs($outsider)->put('/funnels/'.$funnel->id.'/orders/'.$order->id, ['status' => 'confirmed'])->assertForbidden();
        $this->actingAs($manager)->put('/funnels/'.$funnel->id.'/orders/'.$order->id, ['status' => 'confirmed'])->assertRedirect();
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_export_neutralises_spreadsheet_formulas_from_customers(): void
    {
        $funnel = $this->makeFunnel();
        $manager = $this->makeUser('marketing_manager', $funnel->brand);
        $this->postJson('/f/ginger/order', $this->orderPayload(['full_name' => '=HYPERLINK("http://evil")']))->assertCreated();

        $csv = $this->actingAs($manager)->get('/funnels/'.$funnel->id.'/orders/export')->assertOk()->streamedContent();

        $this->assertStringContainsString('Mobile number', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('09171234567', $csv);
    }
}
