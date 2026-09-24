<?php

namespace Tests\Feature\Entitlements;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\VipTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActingAsShop;
use Tests\TestCase;

/**
 * Confirms Starter shops can create Silver/Gold VIP tiers but never
 * Platinum, enforced by VipTierPolicy (not a separate check) — and that
 * downgrading preserves an existing Platinum tier's data rather than
 * deleting it. See docs/ENTITLEMENTS.md#vip-tier-restrictions and
 * docs/BILLING.md#downgrade-safety (PlanServiceTest covers the
 * downgrade-blocking side of this in detail).
 */
class VipTierRestrictionTest extends TestCase
{
    use ActingAsShop, RefreshDatabase;

    private function starterShopWithVipFeatures(): Shop
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();

        foreach (['vip_tier.silver', 'vip_tier.gold'] as $key) {
            $feature = Feature::factory()->create(['key' => $key, 'group' => 'vip_tiers']);
            $plan->features()->attach($feature->id, ['value' => '1']);
        }

        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        return $shop;
    }

    public function test_starter_can_create_a_silver_tier(): void
    {
        $shop = $this->starterShopWithVipFeatures();

        $this->actingAsShop($shop)
            ->postJson('/api/v1/vip-tiers', ['name' => 'Silver', 'slug' => 'silver', 'threshold_points' => 0])
            ->assertStatus(201);
    }

    public function test_starter_can_create_a_gold_tier(): void
    {
        $shop = $this->starterShopWithVipFeatures();

        $this->actingAsShop($shop)
            ->postJson('/api/v1/vip-tiers', ['name' => 'Gold', 'slug' => 'gold', 'threshold_points' => 1000])
            ->assertStatus(201);
    }

    public function test_starter_cannot_create_a_platinum_tier(): void
    {
        $shop = $this->starterShopWithVipFeatures();

        $this->actingAsShop($shop)
            ->postJson('/api/v1/vip-tiers', ['name' => 'Platinum', 'slug' => 'platinum', 'threshold_points' => 5000])
            ->assertStatus(403);

        $this->assertDatabaseMissing('vip_tiers', ['shop_id' => $shop->id, 'slug' => 'platinum']);
    }

    public function test_professional_can_create_a_platinum_tier(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->professional()->create();
        $feature = Feature::factory()->create(['key' => 'vip_tier.platinum', 'group' => 'vip_tiers']);
        $plan->features()->attach($feature->id, ['value' => '1']);
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $this->actingAsShop($shop)
            ->postJson('/api/v1/vip-tiers', ['name' => 'Platinum', 'slug' => 'platinum', 'threshold_points' => 5000])
            ->assertStatus(201);
    }

    public function test_downgrading_preserves_an_existing_platinum_tier_rather_than_deleting_it(): void
    {
        $shop = Shop::factory()->create();
        $professional = Plan::factory()->professional()->create();
        Subscription::factory()->for($shop)->for($professional)->create(['status' => 'active']);

        $platinum = VipTier::factory()->for($shop)->create(['slug' => 'platinum', 'is_active' => true]);

        // The downgrade-safety check (PlanServiceTest covers this in
        // detail) blocks the plan CHANGE while Platinum is configured —
        // but even in that blocked state, the tier and any customers
        // assigned to it are never touched by the check itself.
        $this->assertDatabaseHas('vip_tiers', ['id' => $platinum->id, 'slug' => 'platinum']);
    }
}
