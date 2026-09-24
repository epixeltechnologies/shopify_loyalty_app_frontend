<?php

namespace Tests\Unit\Services\Points;

use App\Exceptions\Points\ManualAdjustmentsDisabledException;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Services\Points\PointAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointAdjustmentServiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_adjustments_are_blocked_when_disabled_for_the_shop(): void
    {
        $shop = Shop::factory()->create();
        ShopSetting::factory()->for($shop)->create(['allow_manual_point_adjustments' => false]);
        $customer = Customer::factory()->for($shop)->create();

        $this->expectException(ManualAdjustmentsDisabledException::class);
        app(PointAdjustmentService::class)->adjust($shop, $customer, 100, 'Test', 'staff@example.com');
    }

    public function test_manual_adjustments_succeed_when_enabled(): void
    {
        $shop = Shop::factory()->create();
        ShopSetting::factory()->for($shop)->create(['allow_manual_point_adjustments' => true]);
        $customer = Customer::factory()->for($shop)->create();

        app(PointAdjustmentService::class)->adjust($shop, $customer, 100, 'Test', 'staff@example.com');

        $this->assertSame(100, $customer->point->fresh()->balance);
    }

    public function test_manual_adjustments_succeed_by_default_with_no_settings_row(): void
    {
        $shop = Shop::factory()->create(); // no ShopSetting row at all
        $customer = Customer::factory()->for($shop)->create();

        app(PointAdjustmentService::class)->adjust($shop, $customer, 50, 'Test', 'staff@example.com');

        $this->assertSame(50, $customer->point->fresh()->balance);
    }

    public function test_system_corrections_are_never_blocked_by_the_merchant_toggle(): void
    {
        $shop = Shop::factory()->create();
        ShopSetting::factory()->for($shop)->create(['allow_manual_point_adjustments' => false]);
        $customer = Customer::factory()->for($shop)->create();

        // correct() is system-initiated, not a merchant action — the
        // "manual adjustment permissions" setting exists specifically
        // to gate the MERCHANT-facing action, not automated corrections.
        app(PointAdjustmentService::class)->correct($shop, $customer, 25, 'Data fix');

        $this->assertSame(25, $customer->point->fresh()->balance);
    }
}
