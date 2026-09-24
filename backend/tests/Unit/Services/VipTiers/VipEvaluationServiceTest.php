<?php

namespace Tests\Unit\Services\VipTiers;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Shop;
use App\Models\VipTier;
use App\Services\Points\PointsLedgerService;
use App\Services\VipTiers\VipEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VipEvaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function tiers(Shop $shop): array
    {
        $silver = VipTier::factory()->for($shop)->create(['slug' => 'silver', 'qualification_method' => 'points_earned', 'threshold_points' => 0, 'sort_order' => 1]);
        $gold = VipTier::factory()->for($shop)->create(['slug' => 'gold', 'qualification_method' => 'points_earned', 'threshold_points' => 1000, 'sort_order' => 2]);

        return [$silver, $gold];
    }

    public function test_evaluate_for_upgrade_only_promotes_a_customer_who_now_qualifies(): void
    {
        $shop = Shop::factory()->create();
        [$silver, $gold] = $this->tiers($shop);
        $customer = Customer::factory()->for($shop)->create(['vip_tier_id' => $silver->id]);
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 1500, PointTransaction::SOURCE_PURCHASE);

        app(VipEvaluationService::class)->evaluateForUpgradeOnly($customer);

        $this->assertSame($gold->id, $customer->fresh()->vip_tier_id);
    }

    public function test_evaluate_for_upgrade_only_never_downgrades(): void
    {
        $shop = Shop::factory()->create();
        [$silver, $gold] = $this->tiers($shop);
        $customer = Customer::factory()->for($shop)->create(['vip_tier_id' => $gold->id]);
        // No points earned — customer no longer meets Gold's threshold by a fresh computation, but has ALREADY been assigned Gold.

        app(VipEvaluationService::class)->evaluateForUpgradeOnly($customer);

        $this->assertSame($gold->id, $customer->fresh()->vip_tier_id); // unchanged — upgrade-only never removes a tier
    }

    public function test_evaluate_fully_applies_a_downgrade(): void
    {
        $shop = Shop::factory()->create();
        [$silver, $gold] = $this->tiers($shop);
        $customer = Customer::factory()->for($shop)->create(['vip_tier_id' => $gold->id]);
        // No qualifying points on record for Gold's lifetime threshold.

        app(VipEvaluationService::class)->evaluateFully($customer);

        $this->assertSame($silver->id, $customer->fresh()->vip_tier_id);
    }

    public function test_evaluate_fully_writes_a_downgrade_history_row(): void
    {
        $shop = Shop::factory()->create();
        [$silver, $gold] = $this->tiers($shop);
        $customer = Customer::factory()->for($shop)->create(['vip_tier_id' => $gold->id]);

        app(VipEvaluationService::class)->evaluateFully($customer);

        $this->assertDatabaseHas('customer_vip_history', [
            'customer_id' => $customer->id, 'from_vip_tier_id' => $gold->id, 'to_vip_tier_id' => $silver->id, 'direction' => 'downgrade',
        ]);
    }

    public function test_a_customer_qualifying_for_multiple_tiers_gets_the_highest_sort_order(): void
    {
        $shop = Shop::factory()->create();
        [$silver, $gold] = $this->tiers($shop);
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 5000, PointTransaction::SOURCE_PURCHASE);

        app(VipEvaluationService::class)->evaluateFully($customer);

        $this->assertSame($gold->id, $customer->fresh()->vip_tier_id); // qualifies for both, gets Gold (higher sort_order)
    }

    public function test_a_tier_unavailable_to_the_shops_plan_is_never_assigned(): void
    {
        $shop = Shop::factory()->create(); // no subscription => no plan => no vip_tier.* features
        $platinum = VipTier::factory()->for($shop)->create(['slug' => 'platinum', 'qualification_method' => 'points_earned', 'threshold_points' => 0, 'sort_order' => 3]);
        $customer = Customer::factory()->for($shop)->create();

        app(VipEvaluationService::class)->evaluateFully($customer);

        $this->assertNull($customer->fresh()->vip_tier_id);
    }

    public function test_a_tier_outside_its_date_window_is_never_assigned(): void
    {
        $shop = Shop::factory()->create();
        $tier = VipTier::factory()->for($shop)->create(['slug' => 'silver', 'qualification_method' => 'points_earned', 'threshold_points' => 0, 'sort_order' => 1, 'ends_at' => now()->subDay()]);
        $customer = Customer::factory()->for($shop)->create();

        app(VipEvaluationService::class)->evaluateFully($customer);

        $this->assertNull($customer->fresh()->vip_tier_id);
    }

    public function test_evaluate_fully_is_idempotent_when_the_tier_is_already_correct(): void
    {
        $shop = Shop::factory()->create();
        [$silver, $gold] = $this->tiers($shop);
        $customer = Customer::factory()->for($shop)->create(['vip_tier_id' => $silver->id]);

        app(VipEvaluationService::class)->evaluateFully($customer);
        app(VipEvaluationService::class)->evaluateFully($customer->fresh());

        // The customer was assigned `silver` directly at creation (no
        // history row for that), and already qualifies for exactly
        // `silver` (no points earned toward Gold) — both evaluateFully()
        // calls are no-ops since the target never differs from current.
        $this->assertSame(0, \App\Models\CustomerVipHistory::query()->where('customer_id', $customer->id)->count());
    }
}
