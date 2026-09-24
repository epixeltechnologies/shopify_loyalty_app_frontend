<?php

namespace Tests\Feature\Api\V1;

use App\Models\Referral;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_referral_belonging_to_another_shop_is_not_accessible(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $otherShopReferral = Referral::factory()->for($shopB)->create();

        $this->actingAsShop($shopA)
            ->getJson("/api/v1/referrals/{$otherShopReferral->id}")
            ->assertStatus(404);
    }

    public function test_clearing_fraud_on_another_shops_referral_is_blocked(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $otherShopReferral = Referral::factory()->for($shopB)->create(['fraud_status' => 'flagged']);

        $this->actingAsShop($shopA)
            ->postJson("/api/v1/referrals/{$otherShopReferral->id}/clear-fraud")
            ->assertStatus(404);
    }

    public function test_referral_settings_can_be_read_and_updated(): void
    {
        $shop = Shop::factory()->create();

        $this->actingAsShop($shop)
            ->patchJson('/api/v1/referrals/settings', ['enabled' => true, 'referrer_reward_points' => 400])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.referrer_reward_points', 400);

        $this->actingAsShop($shop)
            ->getJson('/api/v1/referrals/settings')
            ->assertOk()
            ->assertJsonPath('data.referrer_reward_points', 400);
    }

    public function test_fraud_queue_only_returns_flagged_referrals(): void
    {
        $shop = Shop::factory()->create();
        Referral::factory()->for($shop)->create(['fraud_status' => 'flagged']);
        Referral::factory()->for($shop)->create(['fraud_status' => 'none']);

        $this->actingAsShop($shop)
            ->getJson('/api/v1/referrals/fraud-queue')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
