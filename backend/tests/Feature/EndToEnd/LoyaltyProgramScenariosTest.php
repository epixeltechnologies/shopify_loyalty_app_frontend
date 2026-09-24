<?php

namespace Tests\Feature\EndToEnd;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\ReferralSettings;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\VipTier;
use App\Services\Points\PointsLedgerService;
use App\Services\Referrals\ReferralAttributionService;
use App\Services\Referrals\ReferralQualificationService;
use App\Services\Referrals\ReferralRewardService;
use App\Services\Rewards\RewardRedemptionService;
use App\Services\VipTiers\VipEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SignsShopifyWebhooks;
use Tests\TestCase;

/**
 * QA PASS: the 5 named end-to-end scenarios from the QA task, each
 * chaining several REAL services/webhook entry points together rather
 * than mocking the business logic under test — these prove the
 * milestones actually integrate correctly with each other, which no
 * single-domain unit test can show on its own.
 */
class LoyaltyProgramScenariosTest extends TestCase
{
    use RefreshDatabase, SignsShopifyWebhooks;

    private function subscribedShop(string $planType = 'starter'): Shop
    {
        $shop = Shop::factory()->create();
        $plan = $planType === 'starter' ? Plan::factory()->starter()->create() : Plan::factory()->professional()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        return $shop;
    }

    /**
     * Scenario 1: Install app -> subscribe -> configure points -> create
     * reward -> customer orders -> points awarded -> customer redeems ->
     * Shopify discount created.
     */
    public function test_scenario_1_full_purchase_to_redemption_flow(): void
    {
        $shop = $this->subscribedShop();
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);
        $reward = Reward::factory()->for($shop)->percentageDiscount(10)->create(['points_cost' => 50, 'status' => 'active']);

        // Customer places a qualifying order via the real webhook pipeline.
        $this->postWebhook('orders/create', [
            'id' => 501, 'financial_status' => 'paid', 'subtotal_price' => '100.00', 'total_price' => '100.00',
            'customer' => ['id' => 8001],
        ], webhookId: 'e2e-scenario-1-order', shopDomain: $shop->shopify_domain)->assertOk();

        $customer = Customer::query()->where('shop_id', $shop->id)->where('shopify_customer_id', '8001')->firstOrFail();
        $this->assertSame(100, $customer->point->fresh()->balance);

        // Customer redeems the reward — reuses the centralized redemption service.
        Http::fake(['*' => Http::response([
            'data' => ['discountCodeBasicCreate' => ['codeDiscountNode' => ['id' => 'gid://shopify/DiscountCodeNode/1', 'codeDiscount' => ['codes' => ['nodes' => [['code' => 'LOY-E2E1']]]]], 'userErrors' => []]],
        ], 200)]);

        $redemption = app(RewardRedemptionService::class)->redeem($customer, $reward);
        $this->assertSame('pending', $redemption->status);
        $this->assertSame(50, $customer->point->fresh()->balance);

        // The queued fulfillment job completes the discount creation.
        $job = new \App\Jobs\Rewards\FulfillRewardRedemptionJob($redemption);
        $job->handle(app(\App\Services\Rewards\ShopifyDiscountService::class));

        $this->assertSame('completed', $redemption->fresh()->status);
        $this->assertSame('LOY-E2E1', $redemption->fresh()->shopify_discount_code);
    }

    /** Scenario 2: Referral end-to-end — click, registration, qualifying order, reward issuance. */
    public function test_scenario_2_full_referral_flow(): void
    {
        $shop = $this->subscribedShop();
        ReferralSettings::factory()->for($shop)->create(['enabled' => true, 'referrer_reward_points' => 500, 'referee_reward_points' => 100, 'reward_delay_days' => 0]);
        $referrer = Customer::factory()->for($shop)->create(['email' => 'referrer@example.com']);

        $referral = app(ReferralAttributionService::class)->recordClick($shop, $referrer->referral_code, 'visitor-e2e-2');
        $this->assertSame('clicked', $referral->status);

        $friend = Customer::factory()->for($shop)->create(['email' => 'friend@example.com']);
        app(ReferralAttributionService::class)->attributeRegistration($shop, 'visitor-e2e-2', $friend);
        $this->assertSame('registered', $referral->fresh()->status);

        $order = \App\Models\Order::factory()->for($shop)->for($friend)->create(['financial_status' => 'paid', 'total_cents' => 5000]);
        app(ReferralQualificationService::class)->evaluateOrder($order);
        $this->assertSame('qualified', $referral->fresh()->status);

        $rewarded = app(ReferralRewardService::class)->reward($referral->fresh());
        $this->assertTrue($rewarded);
        $this->assertSame('rewarded', $referral->fresh()->status);
        $this->assertSame(500, $referrer->point->fresh()->balance);
        $this->assertSame(100, $friend->point->fresh()->balance);
    }

    /** Scenario 3: Customer reaches Gold -> tier upgraded -> benefits applied to future point earning. */
    public function test_scenario_3_vip_upgrade_and_benefits_applied(): void
    {
        $shop = $this->subscribedShop('professional');
        VipTier::factory()->for($shop)->create(['slug' => 'silver', 'qualification_method' => 'points_earned', 'threshold_points' => 0, 'sort_order' => 1, 'perks' => ['points_multiplier' => 1.0]]);
        $gold = VipTier::factory()->for($shop)->create(['slug' => 'gold', 'qualification_method' => 'points_earned', 'threshold_points' => 1000, 'sort_order' => 2, 'perks' => ['points_multiplier' => 1.5]]);
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);
        $customer = Customer::factory()->for($shop)->create();

        // First order pushes the customer to Gold (1000+ points earned).
        app(\App\Services\Points\PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 1200, PointTransaction::SOURCE_PURCHASE);
        app(VipEvaluationService::class)->evaluateForUpgradeOnly($customer);
        $this->assertSame($gold->id, $customer->fresh()->vip_tier_id);

        // A subsequent order should now earn at Gold's 1.5x multiplier.
        $order = \App\Models\Order::factory()->for($shop)->for($customer)->create(['financial_status' => 'paid', 'subtotal_cents' => 10000, 'total_cents' => 10000]);
        $posted = app(\App\Services\Points\PointsAccrualService::class)->accrueForOrder($customer->fresh(), $order);

        $this->assertSame(150, $posted->first()->points); // $100 * 1 point/$ * 1.5x Gold multiplier
    }

    /** Scenario 4: Order refunded -> previously awarded points reversed. */
    public function test_scenario_4_refund_reverses_previously_awarded_points(): void
    {
        $shop = $this->subscribedShop();
        PointRule::factory()->for($shop)->create(['type' => 'points_per_dollar', 'config' => ['points_per_dollar' => 1], 'status' => 'active']);

        $this->postWebhook('orders/create', [
            'id' => 502, 'financial_status' => 'paid', 'subtotal_price' => '80.00', 'total_price' => '80.00',
            'customer' => ['id' => 8002],
        ], webhookId: 'e2e-scenario-4-order', shopDomain: $shop->shopify_domain)->assertOk();

        $customer = Customer::query()->where('shop_id', $shop->id)->where('shopify_customer_id', '8002')->firstOrFail();
        $this->assertSame(80, $customer->point->fresh()->balance);

        $this->postWebhook('refunds/create', [
            'id' => 9001, 'order_id' => 502, 'transactions' => [['amount' => '80.00']],
        ], webhookId: 'e2e-scenario-4-refund', shopDomain: $shop->shopify_domain)->assertOk();

        $this->assertSame(0, $customer->fresh()->point->fresh()->balance);
        $this->assertDatabaseHas('point_transactions', ['customer_id' => $customer->id, 'source' => PointTransaction::SOURCE_REFUND_REVERSAL]);
    }

    /** Scenario 5: Downgrade Professional -> Starter — Platinum-only features restricted, existing data preserved. */
    public function test_scenario_5_plan_downgrade_restricts_features_but_preserves_data(): void
    {
        $shop = $this->subscribedShop('professional');
        $platinum = VipTier::factory()->for($shop)->create(['slug' => 'platinum', 'sort_order' => 3]);
        $customer = Customer::factory()->for($shop)->create(['vip_tier_id' => $platinum->id]);
        $reward = Reward::factory()->for($shop)->create(['status' => 'active']);

        // Downgrade: end the active Professional subscription, start a Starter one.
        $shop->subscriptions()->where('status', 'active')->delete();
        Subscription::factory()->for($shop)->for(Plan::factory()->starter()->create())->create(['status' => 'active']);
        $shop->refresh();

        // Existing Platinum tier, assignment, and history are untouched.
        $this->assertDatabaseHas('vip_tiers', ['id' => $platinum->id, 'slug' => 'platinum']);
        $this->assertSame($platinum->id, $customer->fresh()->vip_tier_id);

        // But Platinum is no longer available for NEW qualification, and creating a new Platinum tier is blocked.
        $this->assertFalse($platinum->fresh()->isAvailableToShop());

        $this->actingAsShop($shop)
            ->postJson('/api/v1/vip-tiers', ['name' => 'New Platinum Tier', 'slug' => 'platinum', 'qualification_method' => 'points_earned', 'threshold_points' => 1, 'evaluation_period' => 'lifetime'])
            ->assertStatus(403);

        // The existing reward the shop already had remains fully functional (nothing was deleted or disabled by the downgrade itself).
        $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'status' => 'active']);
    }
}
