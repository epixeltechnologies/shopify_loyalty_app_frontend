<?php

namespace Tests\Unit\Services\VipTiers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\Shop;
use App\Models\VipTier;
use App\Services\Points\PointsLedgerService;
use App\Services\VipTiers\VipQualificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VipQualificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_points_earned_method_uses_lifetime_earned_for_lifetime_period(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 1000, PointTransaction::SOURCE_PURCHASE);
        $tier = VipTier::factory()->for($shop)->create(['qualification_method' => 'points_earned', 'threshold_points' => 500, 'evaluation_period' => 'lifetime']);

        $this->assertTrue(app(VipQualificationService::class)->qualifies($customer, $tier));
    }

    public function test_points_earned_below_threshold_does_not_qualify(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 100, PointTransaction::SOURCE_PURCHASE);
        $tier = VipTier::factory()->for($shop)->create(['qualification_method' => 'points_earned', 'threshold_points' => 500, 'evaluation_period' => 'lifetime']);

        $this->assertFalse(app(VipQualificationService::class)->qualifies($customer, $tier));
    }

    public function test_total_spend_method_sums_paid_orders(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        Order::factory()->for($shop)->for($customer)->create(['financial_status' => 'paid', 'total_cents' => 30000]);
        Order::factory()->for($shop)->for($customer)->create(['financial_status' => 'paid', 'total_cents' => 25000]);
        Order::factory()->for($shop)->for($customer)->create(['financial_status' => 'pending', 'total_cents' => 100000]); // unpaid, excluded
        $tier = VipTier::factory()->for($shop)->create(['qualification_method' => 'total_spend', 'minimum_spend_cents' => 50000, 'evaluation_period' => 'lifetime']);

        $this->assertTrue(app(VipQualificationService::class)->qualifies($customer, $tier));
    }

    public function test_order_count_method_counts_paid_orders_only(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        Order::factory()->for($shop)->for($customer)->count(3)->create(['financial_status' => 'paid']);
        Order::factory()->for($shop)->for($customer)->create(['financial_status' => 'pending']);
        $tier = VipTier::factory()->for($shop)->create(['qualification_method' => 'order_count', 'minimum_orders' => 3, 'evaluation_period' => 'lifetime']);

        $this->assertTrue(app(VipQualificationService::class)->qualifies($customer, $tier));
    }

    public function test_calendar_year_period_excludes_orders_from_last_year(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);
        $customer = Customer::factory()->for($shop)->create();
        Order::factory()->for($shop)->for($customer)->create(['financial_status' => 'paid', 'total_cents' => 100000, 'created_at' => now()->subYear()]);
        $tier = VipTier::factory()->for($shop)->create(['qualification_method' => 'total_spend', 'minimum_spend_cents' => 50000, 'evaluation_period' => 'calendar_year']);

        $this->assertFalse(app(VipQualificationService::class)->qualifies($customer, $tier));
    }

    public function test_calendar_year_period_includes_orders_from_this_year(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);
        $customer = Customer::factory()->for($shop)->create();
        Order::factory()->for($shop)->for($customer)->create(['financial_status' => 'paid', 'total_cents' => 100000, 'created_at' => now()->startOfYear()->addDay()]);
        $tier = VipTier::factory()->for($shop)->create(['qualification_method' => 'total_spend', 'minimum_spend_cents' => 50000, 'evaluation_period' => 'calendar_year']);

        $this->assertTrue(app(VipQualificationService::class)->qualifies($customer, $tier));
    }

    public function test_rolling_period_excludes_orders_older_than_the_window(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);
        $customer = Customer::factory()->for($shop)->create();
        Order::factory()->for($shop)->for($customer)->create(['financial_status' => 'paid', 'total_cents' => 100000, 'created_at' => now()->subDays(100)]);
        $tier = VipTier::factory()->for($shop)->create(['qualification_method' => 'total_spend', 'minimum_spend_cents' => 50000, 'evaluation_period' => 'rolling', 'rolling_period_days' => 90]);

        $this->assertFalse(app(VipQualificationService::class)->qualifies($customer, $tier));
    }

    public function test_rolling_period_includes_orders_within_the_window(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);
        $customer = Customer::factory()->for($shop)->create();
        Order::factory()->for($shop)->for($customer)->create(['financial_status' => 'paid', 'total_cents' => 100000, 'created_at' => now()->subDays(30)]);
        $tier = VipTier::factory()->for($shop)->create(['qualification_method' => 'total_spend', 'minimum_spend_cents' => 50000, 'evaluation_period' => 'rolling', 'rolling_period_days' => 90]);

        $this->assertTrue(app(VipQualificationService::class)->qualifies($customer, $tier));
    }

    public function test_calendar_year_boundary_respects_the_shops_timezone(): void
    {
        // A shop in a timezone far ahead of UTC (e.g. Auckland, UTC+13):
        // an order placed at 11pm Dec 31 UTC is already Jan 1 in
        // Auckland — it must count toward THIS year's total there, even
        // though in UTC it's still technically last year.
        $shop = Shop::factory()->create(['timezone' => 'Pacific/Auckland']);
        $customer = Customer::factory()->for($shop)->create();
        Order::factory()->for($shop)->for($customer)->create([
            'financial_status' => 'paid', 'total_cents' => 100000,
            'created_at' => now()->utc()->subYear()->endOfYear()->subHours(2), // ~10pm Dec 31 UTC last year = already Jan 1 in Auckland
        ]);
        $tier = VipTier::factory()->for($shop)->create(['qualification_method' => 'total_spend', 'minimum_spend_cents' => 50000, 'evaluation_period' => 'calendar_year']);

        $this->assertTrue(app(VipQualificationService::class)->qualifies($customer, $tier));
    }
}
