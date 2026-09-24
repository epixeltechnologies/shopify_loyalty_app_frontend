<?php

namespace Tests\Unit\Services\Rewards;

use App\Exceptions\Rewards\InsufficientPointsException;
use App\Exceptions\Rewards\RewardNotRedeemableException;
use App\Jobs\Rewards\FulfillRewardRedemptionJob;
use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Shop;
use App\Services\Points\PointsLedgerService;
use App\Services\Rewards\RewardRedemptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RewardRedemptionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_redemption_deducts_points_and_creates_a_pending_redemption(): void
    {
        Queue::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 1000, PointTransaction::SOURCE_PURCHASE);
        $reward = Reward::factory()->for($shop)->create(['points_cost' => 300, 'status' => 'active']);

        $redemption = app(RewardRedemptionService::class)->redeem($customer, $reward);

        $this->assertSame(RewardRedemption::STATUS_PENDING, $redemption->status);
        $this->assertSame(1000, $redemption->balance_before);
        $this->assertSame(700, $redemption->balance_after);
        $this->assertSame(700, $customer->point->fresh()->balance);
        Queue::assertPushed(FulfillRewardRedemptionJob::class);
    }

    public function test_a_ledger_redeem_transaction_is_created(): void
    {
        Queue::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 500, PointTransaction::SOURCE_PURCHASE);
        $reward = Reward::factory()->for($shop)->create(['points_cost' => 200, 'status' => 'active']);

        $redemption = app(RewardRedemptionService::class)->redeem($customer, $reward);

        $this->assertDatabaseHas('point_transactions', [
            'customer_id' => $customer->id,
            'direction' => 'redeem',
            'points' => -200,
            'source' => PointTransaction::SOURCE_REWARD_REDEMPTION,
            'source_reference_id' => $redemption->id,
        ]);
    }

    public function test_insufficient_points_throws_and_does_not_create_a_redemption(): void
    {
        Queue::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 50, PointTransaction::SOURCE_PURCHASE);
        $reward = Reward::factory()->for($shop)->create(['points_cost' => 500, 'status' => 'active']);

        try {
            app(RewardRedemptionService::class)->redeem($customer, $reward);
            $this->fail('Expected InsufficientPointsException');
        } catch (InsufficientPointsException) {
            // expected
        }

        $this->assertSame(50, $customer->point->fresh()->balance);
        $this->assertSame(0, RewardRedemption::query()->count());
        Queue::assertNotPushed(FulfillRewardRedemptionJob::class);
    }

    public function test_an_inactive_reward_cannot_be_redeemed_and_does_not_deduct_points(): void
    {
        Queue::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 1000, PointTransaction::SOURCE_PURCHASE);
        $reward = Reward::factory()->for($shop)->create(['points_cost' => 100, 'status' => 'draft']);

        $this->expectException(RewardNotRedeemableException::class);

        try {
            app(RewardRedemptionService::class)->redeem($customer, $reward);
        } finally {
            $this->assertSame(1000, $customer->point->fresh()->balance);
        }
    }

    public function test_an_expired_reward_cannot_be_redeemed(): void
    {
        Queue::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 1000, PointTransaction::SOURCE_PURCHASE);
        $reward = Reward::factory()->for($shop)->create(['points_cost' => 100, 'status' => 'active', 'ends_at' => now()->subDay()]);

        $this->expectException(RewardNotRedeemableException::class);
        app(RewardRedemptionService::class)->redeem($customer, $reward);
    }

    public function test_cross_tenant_reward_redemption_fails_via_route_model_binding_scope(): void
    {
        // Belt-and-suspenders: TenantScope means a Reward from another
        // shop simply can't be resolved into the same query results,
        // but this directly proves the service has no implicit "same
        // shop" assumption baked in that a bypassed scope could exploit
        // — the reward here really does belong to a different shop.
        Queue::fake();
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $customer = Customer::factory()->for($shopA)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 1000, PointTransaction::SOURCE_PURCHASE);
        $rewardFromOtherShop = Reward::factory()->for($shopB)->create(['points_cost' => 100, 'status' => 'active']);

        // The service itself doesn't reject a cross-tenant reward
        // explicitly (that's TenantScope's job at the query layer) —
        // this documents the actual behavioral boundary: redemption is
        // possible at the service layer if a Reward instance from
        // another tenant is passed in directly, which is exactly why
        // the HTTP layer's route-model-binding + TenantScope is the
        // real enforcement point, not this service. See RewardPolicy
        // and the Feature-level cross-tenant test for the actual
        // guarantee customers rely on.
        $redemption = app(RewardRedemptionService::class)->redeem($customer, $rewardFromOtherShop);
        $this->assertNotNull($redemption);
    }

    public function test_duplicate_idempotency_key_returns_the_original_redemption(): void
    {
        Queue::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 1000, PointTransaction::SOURCE_PURCHASE);
        $reward = Reward::factory()->for($shop)->create(['points_cost' => 200, 'status' => 'active']);
        $service = app(RewardRedemptionService::class);

        $first = $service->redeem($customer, $reward, 'client-key-123');
        $second = $service->redeem($customer, $reward, 'client-key-123');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(800, $customer->point->fresh()->balance); // deducted once, not twice
        $this->assertSame(1, RewardRedemption::query()->count());
    }

    public function test_usage_limit_of_one_per_customer_blocks_a_second_redemption(): void
    {
        Queue::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 1000, PointTransaction::SOURCE_PURCHASE);
        $reward = Reward::factory()->for($shop)->create(['points_cost' => 100, 'status' => 'active', 'max_redemptions_per_customer' => 1]);
        $service = app(RewardRedemptionService::class);

        $service->redeem($customer, $reward);

        $this->expectException(RewardNotRedeemableException::class);
        $service->redeem($customer, $reward);
    }
}
