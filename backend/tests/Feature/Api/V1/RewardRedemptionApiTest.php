<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Shop;
use App\Models\Subscription;
use App\Services\Points\PointsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RewardRedemptionApiTest extends TestCase
{
    use RefreshDatabase;

    private function subscribedShop(): Shop
    {
        $shop = Shop::factory()->create();
        Subscription::factory()->for($shop)->for(Plan::factory()->starter()->create())->create(['status' => 'active']);

        return $shop;
    }

    public function test_a_valid_redemption_request_returns_201_with_a_pending_redemption(): void
    {
        Queue::fake();
        $shop = $this->subscribedShop();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 1000, PointTransaction::SOURCE_PURCHASE);
        $reward = Reward::factory()->for($shop)->create(['points_cost' => 300, 'status' => 'active']);

        $this->actingAsShop($shop)
            ->postJson("/api/v1/customers/{$customer->id}/rewards/{$reward->id}/redeem")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.points_spent', 300)
            ->assertJsonPath('data.balance_after', 700);
    }

    public function test_insufficient_points_returns_422_with_the_correct_error_code(): void
    {
        $shop = $this->subscribedShop();
        $customer = Customer::factory()->for($shop)->create();
        $reward = Reward::factory()->for($shop)->create(['points_cost' => 500, 'status' => 'active']);

        $this->actingAsShop($shop)
            ->postJson("/api/v1/customers/{$customer->id}/rewards/{$reward->id}/redeem")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'INSUFFICIENT_POINTS');
    }

    public function test_a_reward_belonging_to_another_shop_returns_404_not_leaked_cross_tenant(): void
    {
        $shopA = $this->subscribedShop();
        $shopB = $this->subscribedShop();
        $customer = Customer::factory()->for($shopA)->create();
        $rewardFromOtherShop = Reward::factory()->for($shopB)->create(['status' => 'active']);

        $this->actingAsShop($shopA)
            ->postJson("/api/v1/customers/{$customer->id}/rewards/{$rewardFromOtherShop->id}/redeem")
            ->assertStatus(404);
    }

    public function test_a_customer_belonging_to_another_shop_returns_404(): void
    {
        $shopA = $this->subscribedShop();
        $shopB = $this->subscribedShop();
        $customerFromOtherShop = Customer::factory()->for($shopB)->create();
        $reward = Reward::factory()->for($shopA)->create(['status' => 'active']);

        $this->actingAsShop($shopA)
            ->postJson("/api/v1/customers/{$customerFromOtherShop->id}/rewards/{$reward->id}/redeem")
            ->assertStatus(404);
    }

    public function test_creating_a_6th_active_reward_on_starter_plan_is_blocked(): void
    {
        $shop = $this->subscribedShop(); // starter plan: max_active_rewards = 5
        Reward::factory()->for($shop)->count(5)->create(['status' => 'active']);

        $this->actingAsShop($shop)
            ->postJson('/api/v1/rewards', [
                'name' => 'One too many', 'type' => 'percentage_discount',
                'points_cost' => 100, 'value' => ['percentage' => 5], 'status' => 'active',
            ])
            ->assertStatus(402)
            ->assertJsonPath('error_code', 'LIMIT_REACHED');
    }

    public function test_a_6th_draft_reward_is_not_blocked_by_the_active_limit(): void
    {
        $shop = $this->subscribedShop();
        Reward::factory()->for($shop)->count(5)->create(['status' => 'active']);

        $this->actingAsShop($shop)
            ->postJson('/api/v1/rewards', [
                'name' => 'Just a draft', 'type' => 'percentage_discount',
                'points_cost' => 100, 'value' => ['percentage' => 5], 'status' => 'draft',
            ])
            ->assertStatus(201);
    }

    public function test_available_rewards_endpoint_only_returns_active_catalog(): void
    {
        $shop = $this->subscribedShop();
        Reward::factory()->for($shop)->create(['status' => 'active', 'name' => 'Active One']);
        Reward::factory()->for($shop)->create(['status' => 'draft', 'name' => 'Draft One']);

        $this->actingAsShop($shop)
            ->getJson('/api/v1/rewards/available')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active One');
    }
}
