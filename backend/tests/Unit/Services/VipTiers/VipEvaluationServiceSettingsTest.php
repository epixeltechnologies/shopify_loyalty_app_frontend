<?php

namespace Tests\Unit\Services\VipTiers;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Shop;
use App\Models\VipSettings;
use App\Models\VipTier;
use App\Services\Points\PointsLedgerService;
use App\Services\VipTiers\VipEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VipEvaluationServiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_is_skipped_entirely_when_the_vip_program_is_disabled(): void
    {
        $shop = Shop::factory()->create();
        VipSettings::factory()->for($shop)->create(['enabled' => false]);
        $tier = VipTier::factory()->for($shop)->create(['slug' => 'silver', 'qualification_method' => 'points_earned', 'threshold_points' => 0, 'sort_order' => 1]);
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 1000, PointTransaction::SOURCE_PURCHASE);

        app(VipEvaluationService::class)->evaluateForUpgradeOnly($customer);

        $this->assertNull($customer->fresh()->vip_tier_id);
    }

    public function test_evaluation_proceeds_normally_when_no_vip_settings_row_exists_yet(): void
    {
        // No VipSettings row at all — must default to enabled, or this
        // would silently break every shop already using VIP tiers the
        // moment this settings table was introduced.
        $shop = Shop::factory()->create();
        $tier = VipTier::factory()->for($shop)->create(['slug' => 'silver', 'qualification_method' => 'points_earned', 'threshold_points' => 0, 'sort_order' => 1]);
        $customer = Customer::factory()->for($shop)->create();

        app(VipEvaluationService::class)->evaluateForUpgradeOnly($customer);

        $this->assertSame($tier->id, $customer->fresh()->vip_tier_id);
    }

    public function test_evaluation_proceeds_when_explicitly_enabled(): void
    {
        $shop = Shop::factory()->create();
        VipSettings::factory()->for($shop)->create(['enabled' => true]);
        $tier = VipTier::factory()->for($shop)->create(['slug' => 'silver', 'qualification_method' => 'points_earned', 'threshold_points' => 0, 'sort_order' => 1]);
        $customer = Customer::factory()->for($shop)->create();

        app(VipEvaluationService::class)->evaluateForUpgradeOnly($customer);

        $this->assertSame($tier->id, $customer->fresh()->vip_tier_id);
    }
}
