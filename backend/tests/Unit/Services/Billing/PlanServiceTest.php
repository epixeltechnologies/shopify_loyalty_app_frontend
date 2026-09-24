<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Customer;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\SubscriptionUsage;
use App\Models\VipTier;
use App\Services\Billing\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedFeature(Plan $plan, string $key, string $group): Feature
    {
        $feature = Feature::factory()->create(['key' => $key, 'group' => $group]);
        $plan->features()->attach($feature->id, ['value' => '1']);

        return $feature;
    }

    public function test_is_downgrade_compares_by_sort_order_not_price(): void
    {
        $service = app(PlanService::class);
        $starter = Plan::factory()->starter()->create(['sort_order' => 1]);
        $professional = Plan::factory()->professional()->create(['sort_order' => 2]);

        $this->assertTrue($service->isDowngrade($professional, $starter));
        $this->assertFalse($service->isDowngrade($starter, $professional));
    }

    public function test_downgrade_is_allowed_when_within_all_limits(): void
    {
        $shop = Shop::factory()->create();
        $professional = Plan::factory()->professional()->create();
        $starter = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($professional)->create(['status' => 'active']);

        Customer::factory()->for($shop)->count(10)->create(); // well within Starter's 500 limit

        $result = app(PlanService::class)->checkDowngradeEligibility($shop, $starter);

        $this->assertTrue($result->eligible);
        $this->assertEmpty($result->blockers);
    }

    public function test_downgrade_is_blocked_when_customer_count_exceeds_target_limit(): void
    {
        $shop = Shop::factory()->create();
        $professional = Plan::factory()->professional()->create();
        $starter = Plan::factory()->starter()->create(); // max_active_customers = 500
        Subscription::factory()->for($shop)->for($professional)->create(['status' => 'active']);

        SubscriptionUsage::query()->create([
            'shop_id' => $shop->id,
            'metric' => SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS,
            'value' => 600, // exceeds Starter's 500
        ]);

        $result = app(PlanService::class)->checkDowngradeEligibility($shop, $starter);

        $this->assertFalse($result->eligible);
        $this->assertSame('customer_limit_exceeded', $result->blockers[0]['code']);
    }

    public function test_downgrade_is_blocked_when_a_vip_tier_is_unavailable_on_the_target_plan(): void
    {
        $shop = Shop::factory()->create();
        $professional = Plan::factory()->professional()->create();
        $starter = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($professional)->create(['status' => 'active']);

        $this->seedFeature($starter, 'vip_tier.silver', 'vip_tiers');
        $this->seedFeature($starter, 'vip_tier.gold', 'vip_tiers');
        // Starter deliberately has no vip_tier.platinum feature.

        VipTier::factory()->for($shop)->create(['slug' => 'platinum', 'is_active' => true]);

        $result = app(PlanService::class)->checkDowngradeEligibility($shop, $starter);

        $this->assertFalse($result->eligible);
        $this->assertSame('vip_tier_unavailable', $result->blockers[0]['code']);
    }

    public function test_downgrade_warns_but_does_not_block_on_lost_boolean_features(): void
    {
        $shop = Shop::factory()->create();
        $professional = Plan::factory()->professional()->create();
        $starter = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($professional)->create(['status' => 'active']);

        $this->seedFeature($professional, 'export.csv', 'reporting');
        // Starter has no export.csv feature — this must be a WARNING, not a blocker.

        $result = app(PlanService::class)->checkDowngradeEligibility($shop, $starter);

        $this->assertTrue($result->eligible);
        $this->assertEmpty($result->blockers);
        $this->assertNotEmpty($result->warnings);
        $this->assertSame('feature_unavailable', $result->warnings[0]['code']);
    }
}
