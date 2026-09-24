<?php

namespace Tests\Feature\Security;

use App\Models\AnalyticsExport;
use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Shop;
use App\Models\VipTier;
use App\Services\Points\PointsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SECURITY AUDIT: a single, cross-cutting sweep proving Shop A can
 * never read Shop B's data through ANY of the major domains this app
 * exposes — customers, points, rewards, referrals, VIP, analytics
 * exports, settings. Each domain already has its own scattered
 * tenant-isolation tests from the milestone that built it; this file
 * exists specifically so tenant isolation is verified as ONE
 * deliberate security property, not just an incidental side effect of
 * per-feature tests written for other reasons.
 */
class CrossTenantIsolationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_a_cannot_read_shop_bs_customer(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $customerB = Customer::factory()->for($shopB)->create();

        $this->actingAsShop($shopA)->getJson("/api/v1/customers/{$customerB->id}")->assertStatus(404);
    }

    public function test_shop_a_cannot_read_shop_bs_customer_points_history(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $customerB = Customer::factory()->for($shopB)->create();
        app(PointsLedgerService::class)->post($customerB, PointTransaction::DIRECTION_EARN, 500, PointTransaction::SOURCE_PURCHASE);

        $this->actingAsShop($shopA)->getJson("/api/v1/customers/{$customerB->id}/points")->assertStatus(404);
    }

    public function test_shop_a_cannot_read_shop_bs_reward(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $rewardB = Reward::factory()->for($shopB)->create();

        $this->actingAsShop($shopA)->getJson("/api/v1/rewards/{$rewardB->id}")->assertStatus(404);
    }

    public function test_shop_as_reward_list_never_includes_shop_bs_rewards(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        Reward::factory()->for($shopA)->create(['name' => 'Shop A Reward']);
        Reward::factory()->for($shopB)->create(['name' => 'Shop B Reward']);

        $response = $this->actingAsShop($shopA)->getJson('/api/v1/rewards')->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Shop A Reward'));
        $this->assertFalse($names->contains('Shop B Reward'));
    }

    public function test_shop_a_cannot_read_shop_bs_reward_redemption_history(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $customerB = Customer::factory()->for($shopB)->create();
        $rewardB = Reward::factory()->for($shopB)->create();
        RewardRedemption::factory()->for($shopB)->for($customerB)->for($rewardB)->create();

        $response = $this->actingAsShop($shopA)->getJson('/api/v1/reward-redemptions')->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    public function test_shop_a_cannot_read_shop_bs_referral(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $referralB = Referral::factory()->for($shopB)->create();

        $this->actingAsShop($shopA)->getJson("/api/v1/referrals/{$referralB->id}")->assertStatus(404);
    }

    public function test_shop_a_cannot_read_shop_bs_vip_tier(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $tierB = VipTier::factory()->for($shopB)->create();

        $this->actingAsShop($shopA)->getJson("/api/v1/vip-tiers/{$tierB->id}")->assertStatus(404);
    }

    public function test_shop_as_analytics_never_reflects_shop_bs_activity(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $customerB = Customer::factory()->for($shopB)->create();
        app(PointsLedgerService::class)->post($customerB, PointTransaction::DIRECTION_EARN, 999999, PointTransaction::SOURCE_PURCHASE);

        $response = $this->actingAsShop($shopA)
            ->getJson('/api/v1/analytics/reports/points?preset=this_year')
            ->assertOk();

        $this->assertSame(0, $response->json('data.summary.total_earned'));
    }

    public function test_shop_a_cannot_access_shop_bs_analytics_export(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $exportB = AnalyticsExport::factory()->for($shopB)->create(['status' => 'completed', 'expires_at' => now()->addDay()]);

        $this->actingAsShop($shopA)->getJson("/api/v1/analytics/exports/{$exportB->id}")->assertStatus(404);
    }

    public function test_shop_as_settings_are_never_shop_bs_settings(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        $this->actingAsShop($shopB)->patchJson('/api/v1/settings', ['program_name' => 'Shop B Program'])->assertOk();
        $response = $this->actingAsShop($shopA)->getJson('/api/v1/settings')->assertOk();

        $this->assertNotSame('Shop B Program', $response->json('data.program_name'));
    }

    public function test_shop_a_cannot_redeem_a_reward_on_behalf_of_shop_bs_customer(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $customerB = Customer::factory()->for($shopB)->create();
        $rewardA = Reward::factory()->for($shopA)->create(['status' => 'active']);

        // Even naming a real, existing customer ID from another shop in
        // the URL must not let shop A act on it — TenantScope means the
        // route-model binding itself can't resolve customerB under
        // shop A's tenant context.
        $this->actingAsShop($shopA)
            ->postJson("/api/v1/customers/{$customerB->id}/rewards/{$rewardA->id}/redeem")
            ->assertStatus(404);
    }
}
