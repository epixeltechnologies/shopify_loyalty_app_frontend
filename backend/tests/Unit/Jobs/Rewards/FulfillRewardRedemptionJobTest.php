<?php

namespace Tests\Unit\Jobs\Rewards;

use App\Jobs\Rewards\FulfillRewardRedemptionJob;
use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Shop;
use App\Services\Points\PointsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FulfillRewardRedemptionJobTest extends TestCase
{
    use RefreshDatabase;

    private function pendingRedemption(Shop $shop, Customer $customer, Reward $reward, int $balanceAfter): RewardRedemption
    {
        return RewardRedemption::factory()->for($shop)->for($customer)->for($reward)->create([
            'points_spent' => $reward->points_cost,
            'balance_before' => $balanceAfter + $reward->points_cost,
            'balance_after' => $balanceAfter,
            'status' => 'pending',
        ]);
    }

    public function test_successful_discount_creation_completes_the_redemption(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '999']);
        $reward = Reward::factory()->for($shop)->percentageDiscount(10)->create(['points_cost' => 200]);
        $redemption = $this->pendingRedemption($shop, $customer, $reward, 800);

        Http::fake([
            "https://{$shop->shopify_domain}/*" => Http::response([
                'data' => [
                    'discountCodeBasicCreate' => [
                        'codeDiscountNode' => ['id' => 'gid://shopify/DiscountCodeNode/555', 'codeDiscount' => ['codes' => ['nodes' => [['code' => 'LOY-ABC123']]]]],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        (new FulfillRewardRedemptionJob($redemption))->handle(app(\App\Services\Rewards\ShopifyDiscountService::class));

        $redemption->refresh();
        $this->assertSame('completed', $redemption->status);
        $this->assertSame('gid://shopify/DiscountCodeNode/555', $redemption->shopify_discount_id);
        $this->assertSame('LOY-ABC123', $redemption->shopify_discount_code);
        $this->assertNotNull($redemption->fulfilled_at);
    }

    public function test_a_shopify_user_error_fails_the_redemption_and_refunds_points(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '999']);
        $reward = Reward::factory()->for($shop)->percentageDiscount(10)->create(['points_cost' => 200]);
        $redemption = $this->pendingRedemption($shop, $customer, $reward, 800);

        Http::fake([
            "https://{$shop->shopify_domain}/*" => Http::response([
                'data' => [
                    'discountCodeBasicCreate' => [
                        'codeDiscountNode' => null,
                        'userErrors' => [['field' => ['code'], 'message' => 'Code has already been taken', 'code' => 'TAKEN']],
                    ],
                ],
            ], 200),
        ]);

        (new FulfillRewardRedemptionJob($redemption))->handle(app(\App\Services\Rewards\ShopifyDiscountService::class));

        $redemption->refresh();
        $this->assertSame('failed', $redemption->status);
        $this->assertNotNull($redemption->failure_reason);
        // points refunded back
        $this->assertSame(1000, $customer->point->fresh()->balance);
        $this->assertDatabaseHas('point_transactions', [
            'customer_id' => $customer->id,
            'source' => PointTransaction::SOURCE_REDEMPTION_REFUND,
            'points' => 200,
        ]);
    }

    public function test_a_shopify_api_failure_fails_the_redemption_and_refunds_points(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '999']);
        $reward = Reward::factory()->for($shop)->percentageDiscount(10)->create(['points_cost' => 150]);
        $redemption = $this->pendingRedemption($shop, $customer, $reward, 850);

        Http::fake(["https://{$shop->shopify_domain}/*" => Http::response([], 500)]);

        (new FulfillRewardRedemptionJob($redemption))->handle(app(\App\Services\Rewards\ShopifyDiscountService::class));

        $redemption->refresh();
        $this->assertSame('failed', $redemption->status);
        $this->assertSame(1000, $customer->point->fresh()->balance);
    }

    public function test_calling_handle_twice_never_double_refunds(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '999']);
        $reward = Reward::factory()->for($shop)->percentageDiscount(10)->create(['points_cost' => 100]);
        $redemption = $this->pendingRedemption($shop, $customer, $reward, 900);

        Http::fake(["https://{$shop->shopify_domain}/*" => Http::response([], 500)]);

        $job = new FulfillRewardRedemptionJob($redemption);
        $job->handle(app(\App\Services\Rewards\ShopifyDiscountService::class));
        $job->handle(app(\App\Services\Rewards\ShopifyDiscountService::class)); // second attempt is a no-op, status already terminal

        $this->assertSame(1000, $customer->point->fresh()->balance); // not 1100
    }

    public function test_free_shipping_reward_uses_the_free_shipping_mutation_and_completes(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '999']);
        $reward = Reward::factory()->for($shop)->freeShipping()->create(['points_cost' => 300]);
        $redemption = $this->pendingRedemption($shop, $customer, $reward, 700);

        Http::fake([
            "https://{$shop->shopify_domain}/*" => Http::response([
                'data' => [
                    'discountCodeFreeShippingCreate' => [
                        'codeDiscountNode' => ['id' => 'gid://shopify/DiscountCodeNode/777', 'codeDiscount' => ['codes' => ['nodes' => [['code' => 'LOY-SHIP01']]]]],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        (new FulfillRewardRedemptionJob($redemption))->handle(app(\App\Services\Rewards\ShopifyDiscountService::class));

        $this->assertSame('completed', $redemption->fresh()->status);
    }
}
