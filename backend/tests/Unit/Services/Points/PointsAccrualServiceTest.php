<?php

namespace Tests\Unit\Services\Points;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PointRule;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Services\Points\PointsAccrualService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointsAccrualServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_accrue_for_order_posts_points_from_an_active_rule(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->for($customer)->create(['subtotal_cents' => 5000, 'total_cents' => 5000]);
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);

        $posted = app(PointsAccrualService::class)->accrueForOrder($customer, $order);

        $this->assertCount(1, $posted);
        $this->assertSame(50, $customer->point->fresh()->balance);
    }

    public function test_multiple_active_rules_stack(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->for($customer)->create(['subtotal_cents' => 2000, 'total_cents' => 2000]);
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['fixed_points_per_order' => 100], 'status' => 'active']);

        app(PointsAccrualService::class)->accrueForOrder($customer, $order);

        $this->assertSame(120, $customer->point->fresh()->balance); // 20 (per-dollar) + 100 (fixed)
    }

    public function test_accrue_for_order_is_idempotent_per_order_and_rule(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->for($customer)->create(['subtotal_cents' => 1000, 'total_cents' => 1000]);
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);

        $accrual = app(PointsAccrualService::class);
        $accrual->accrueForOrder($customer, $order);
        $accrual->accrueForOrder($customer, $order); // simulates orders/updated re-attempting

        $this->assertSame(10, $customer->point->fresh()->balance); // not 20
    }

    public function test_draft_rules_are_never_evaluated(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->for($customer)->create(['subtotal_cents' => 5000, 'total_cents' => 5000]);
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'draft']);

        app(PointsAccrualService::class)->accrueForOrder($customer, $order);

        $this->assertSame(0, $customer->point->fresh()->balance);
    }

    public function test_signup_bonus_is_awarded_once(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        PointRule::factory()->for($shop)->create(['type' => 'signup_bonus', 'config' => ['bonus_points' => 500], 'status' => 'active']);

        $accrual = app(PointsAccrualService::class);
        $first = $accrual->accrueSignupBonus($customer);
        $second = $accrual->accrueSignupBonus($customer);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(500, $customer->point->fresh()->balance);
    }

    public function test_signup_bonus_is_null_with_no_active_rule(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        $this->assertNull(app(PointsAccrualService::class)->accrueSignupBonus($customer));
    }

    public function test_birthday_bonus_is_awarded_once_per_year(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        PointRule::factory()->for($shop)->create(['type' => 'birthday_bonus', 'config' => ['bonus_points' => 100], 'status' => 'active']);

        $accrual = app(PointsAccrualService::class);
        $thisYear = $accrual->accrueBirthdayBonus($customer, 2026);
        $sameYearAgain = $accrual->accrueBirthdayBonus($customer, 2026);
        $nextYear = $accrual->accrueBirthdayBonus($customer, 2027);

        $this->assertSame($thisYear->id, $sameYearAgain->id);
        $this->assertNotSame($thisYear->id, $nextYear->id);
        $this->assertSame(200, $customer->point->fresh()->balance); // once for 2026, once for 2027
    }

    public function test_expires_at_is_set_when_the_shop_has_a_points_expiry_policy(): void
    {
        $shop = Shop::factory()->create();
        ShopSetting::factory()->for($shop)->create(['points_expiry_days' => 365]);
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->for($customer)->create(['subtotal_cents' => 1000, 'total_cents' => 1000]);
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);

        $posted = app(PointsAccrualService::class)->accrueForOrder($customer, $order);

        $this->assertNotNull($posted->first()->expires_at);
    }

    public function test_expires_at_is_null_when_the_shop_has_no_expiry_policy(): void
    {
        $shop = Shop::factory()->create();
        ShopSetting::factory()->for($shop)->create(['points_expiry_days' => null]);
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->for($customer)->create(['subtotal_cents' => 1000, 'total_cents' => 1000]);
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);

        $posted = app(PointsAccrualService::class)->accrueForOrder($customer, $order);

        $this->assertNull($posted->first()->expires_at);
    }
}
