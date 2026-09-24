<?php

namespace Tests\Unit\Services\Points;

use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\PointRule;
use App\Models\Shop;
use App\Services\Points\PointCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = []): Order
    {
        $shop = $overrides['shop'] ?? Shop::factory()->create();
        unset($overrides['shop']);

        return Order::factory()->for($shop)->create(array_merge([
            'subtotal_cents' => 5000,
            'total_cents' => 5000,
            'total_discounts_cents' => 0,
            'total_tax_cents' => 0,
            'total_shipping_cents' => 0,
        ], $overrides));
    }

    private function rule(array $config, Shop $shop): PointRule
    {
        return PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => $config]);
    }

    public function test_basic_points_per_dollar_calculation(): void
    {
        $shop = Shop::factory()->create();
        $order = $this->order(['shop' => $shop, 'subtotal_cents' => 5000]); // $50
        $rule = $this->rule(['points_per_dollar' => 1], $shop);

        $points = app(PointCalculationService::class)->calculateForOrder($rule, $order);

        $this->assertSame(50, $points);
    }

    public function test_fractional_rate_rounds_down(): void
    {
        $shop = Shop::factory()->create();
        $order = $this->order(['shop' => $shop, 'subtotal_cents' => 999]); // $9.99
        $rule = $this->rule(['points_per_dollar' => 0.5], $shop);

        $points = app(PointCalculationService::class)->calculateForOrder($rule, $order);

        $this->assertSame(4, $points); // floor(9.99 * 0.5) = floor(4.995) = 4
    }

    public function test_fixed_bonus_stacks_with_per_dollar_rate(): void
    {
        $shop = Shop::factory()->create();
        $order = $this->order(['shop' => $shop, 'subtotal_cents' => 2000]); // $20
        $rule = $this->rule(['points_per_dollar' => 1, 'fixed_points_per_order' => 25], $shop);

        $this->assertSame(45, app(PointCalculationService::class)->calculateForOrder($rule, $order));
    }

    public function test_minimum_order_amount_blocks_smaller_orders(): void
    {
        $shop = Shop::factory()->create();
        $order = $this->order(['shop' => $shop, 'subtotal_cents' => 1000, 'total_cents' => 1000]);
        $rule = $this->rule(['points_per_dollar' => 1, 'minimum_order_amount_cents' => 2000], $shop);

        $this->assertSame(0, app(PointCalculationService::class)->calculateForOrder($rule, $order));
    }

    public function test_minimum_order_amount_allows_orders_at_or_above_the_threshold(): void
    {
        $shop = Shop::factory()->create();
        $order = $this->order(['shop' => $shop, 'subtotal_cents' => 2000, 'total_cents' => 2000]);
        $rule = $this->rule(['points_per_dollar' => 1, 'minimum_order_amount_cents' => 2000], $shop);

        $this->assertSame(20, app(PointCalculationService::class)->calculateForOrder($rule, $order));
    }

    public function test_discounts_are_excluded_by_default(): void
    {
        $shop = Shop::factory()->create();
        // subtotal is already post-discount per Shopify's own definition
        $order = $this->order(['shop' => $shop, 'subtotal_cents' => 4000, 'total_discounts_cents' => 1000]);
        $rule = $this->rule(['points_per_dollar' => 1], $shop);

        $this->assertSame(40, app(PointCalculationService::class)->calculateForOrder($rule, $order));
    }

    public function test_a_rule_can_opt_in_to_counting_the_pre_discount_amount(): void
    {
        $shop = Shop::factory()->create();
        $order = $this->order(['shop' => $shop, 'subtotal_cents' => 4000, 'total_discounts_cents' => 1000]);
        $rule = $this->rule(['points_per_dollar' => 1, 'exclude_discounts' => false], $shop);

        $this->assertSame(50, app(PointCalculationService::class)->calculateForOrder($rule, $order)); // 4000 + 1000 = 5000 -> $50
    }

    public function test_tax_and_shipping_are_excluded_by_default(): void
    {
        $shop = Shop::factory()->create();
        $order = $this->order(['shop' => $shop, 'subtotal_cents' => 3000, 'total_tax_cents' => 300, 'total_shipping_cents' => 500]);
        $rule = $this->rule(['points_per_dollar' => 1], $shop);

        $this->assertSame(30, app(PointCalculationService::class)->calculateForOrder($rule, $order));
    }

    public function test_product_level_inclusion_filters_out_non_matching_line_items(): void
    {
        $shop = Shop::factory()->create();
        $order = $this->order(['shop' => $shop]);
        OrderLineItem::factory()->for($shop)->for($order)->create(['shopify_product_id' => '111', 'price_cents' => 1000, 'quantity' => 1, 'total_discount_cents' => 0]);
        OrderLineItem::factory()->for($shop)->for($order)->create(['shopify_product_id' => '222', 'price_cents' => 2000, 'quantity' => 1, 'total_discount_cents' => 0]);
        $rule = $this->rule(['points_per_dollar' => 1, 'included_product_ids' => ['111']], $shop);

        $this->assertSame(10, app(PointCalculationService::class)->calculateForOrder($rule, $order->fresh(['lineItems'])));
    }

    public function test_product_level_exclusion_removes_matching_line_items(): void
    {
        $shop = Shop::factory()->create();
        $order = $this->order(['shop' => $shop]);
        OrderLineItem::factory()->for($shop)->for($order)->create(['shopify_product_id' => '111', 'price_cents' => 1000, 'quantity' => 1, 'total_discount_cents' => 0]);
        OrderLineItem::factory()->for($shop)->for($order)->create(['shopify_product_id' => '222', 'price_cents' => 2000, 'quantity' => 1, 'total_discount_cents' => 0]);
        $rule = $this->rule(['points_per_dollar' => 1, 'excluded_product_ids' => ['222']], $shop);

        $this->assertSame(10, app(PointCalculationService::class)->calculateForOrder($rule, $order->fresh(['lineItems'])));
    }

    public function test_no_config_points_per_dollar_key_yields_zero_from_the_rate_component(): void
    {
        $shop = Shop::factory()->create();
        $order = $this->order(['shop' => $shop, 'subtotal_cents' => 5000]);
        $rule = $this->rule(['fixed_points_per_order' => 15], $shop);

        $this->assertSame(15, app(PointCalculationService::class)->calculateForOrder($rule, $order));
    }
}
