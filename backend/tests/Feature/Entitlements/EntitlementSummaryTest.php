<?php

namespace Tests\Feature\Entitlements;

use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActingAsShop;
use Tests\TestCase;

/** Confirms GET /entitlements surfaces the Reward-catalog limit (active_rewards) the frontend billing/rewards pages depend on. */
class EntitlementSummaryTest extends TestCase
{
    use ActingAsShop, RefreshDatabase;

    public function test_summary_includes_the_active_rewards_limit(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create(['max_active_rewards' => 5]);
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $this->actingAsShop($shop)
            ->getJson('/api/v1/entitlements')
            ->assertOk()
            ->assertJsonPath('data.limits.active_rewards.limit', 5)
            ->assertJsonPath('data.limits.active_rewards.used', 0);
    }
}
