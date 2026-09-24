<?php

namespace Tests\Unit\Services\Rewards;

use App\Exceptions\Rewards\DiscountCreationFailedException;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Shop;
use App\Services\Rewards\ShopifyDiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * QA PASS gap fix: no dedicated test previously existed for
 * ShopifyDiscountService's actual mutation-building logic —
 * FulfillRewardRedemptionJobTest exercises the job's orchestration
 * (success/failure/refund) but never asserts on the EXACT GraphQL
 * variables sent to Shopify for each reward type. These tests capture
 * the real outgoing request body and assert on it directly — this is
 * "actual business behavior" (does a percentage reward really send a
 * fraction, not a whole number; is the discount really scoped to only
 * the redeeming customer), not an implementation-detail test.
 */
class ShopifyDiscountServiceTest extends TestCase
{
    use RefreshDatabase;

    private function redemption(Shop $shop, Customer $customer, Reward $reward): RewardRedemption
    {
        return RewardRedemption::factory()->for($shop)->for($customer)->for($reward)->create(['points_spent' => $reward->points_cost]);
    }

    private function fakeSuccessfulBasicResponse(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'discountCodeBasicCreate' => [
                        'codeDiscountNode' => ['id' => 'gid://shopify/DiscountCodeNode/1', 'codeDiscount' => ['codes' => ['nodes' => [['code' => 'LOY-TEST']]]]],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);
    }

    public function test_a_percentage_discount_sends_a_fraction_not_a_whole_number(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '111']);
        $reward = Reward::factory()->for($shop)->percentageDiscount(15)->create();
        $this->fakeSuccessfulBasicResponse();

        app(ShopifyDiscountService::class)->createForRedemption($reward, $customer, $this->redemption($shop, $customer, $reward));

        Http::assertSent(function ($request) {
            $percentage = $request->data()['variables']['basicCodeDiscount']['customerGets']['value']['percentage'] ?? null;

            return $percentage === 0.15; // 15% sent as the fraction Shopify's API actually expects, not "15"
        });
    }

    public function test_a_fixed_discount_sends_the_amount_in_dollars_not_cents(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '111']);
        $reward = Reward::factory()->for($shop)->fixedDiscount(500)->create(); // 500 cents = $5.00
        $this->fakeSuccessfulBasicResponse();

        app(ShopifyDiscountService::class)->createForRedemption($reward, $customer, $this->redemption($shop, $customer, $reward));

        Http::assertSent(function ($request) {
            $amount = $request->data()['variables']['basicCodeDiscount']['customerGets']['value']['discountAmount']['amount'] ?? null;

            return $amount === '5.00';
        });
    }

    public function test_the_discount_is_scoped_to_only_the_redeeming_customer_not_all_customers(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '999']);
        $reward = Reward::factory()->for($shop)->percentageDiscount(10)->create();
        $this->fakeSuccessfulBasicResponse();

        app(ShopifyDiscountService::class)->createForRedemption($reward, $customer, $this->redemption($shop, $customer, $reward));

        Http::assertSent(function ($request) {
            $selection = $request->data()['variables']['basicCodeDiscount']['customerSelection'] ?? null;

            // Must be scoped to exactly this customer's GID — never {"all": true} —
            // this is what makes the discount code unusable by anyone else even
            // if the code string leaked. See ShopifyDiscountService's docblock.
            return $selection === ['customers' => ['add' => ['gid://shopify/Customer/999']]];
        });
    }

    public function test_usage_is_limited_to_once_per_customer(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '111']);
        $reward = Reward::factory()->for($shop)->percentageDiscount(10)->create();
        $this->fakeSuccessfulBasicResponse();

        app(ShopifyDiscountService::class)->createForRedemption($reward, $customer, $this->redemption($shop, $customer, $reward));

        Http::assertSent(function ($request) {
            $vars = $request->data()['variables']['basicCodeDiscount'];

            return $vars['usageLimit'] === 1 && $vars['appliesOncePerCustomer'] === true;
        });
    }

    public function test_a_minimum_purchase_requirement_is_included_when_configured(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '111']);
        $reward = Reward::factory()->for($shop)->percentageDiscount(10)->create(['min_purchase_amount_cents' => 5000]);
        $this->fakeSuccessfulBasicResponse();

        app(ShopifyDiscountService::class)->createForRedemption($reward, $customer, $this->redemption($shop, $customer, $reward));

        Http::assertSent(function ($request) {
            $min = $request->data()['variables']['basicCodeDiscount']['minimumRequirement']['subtotal']['greaterThanOrEqualToSubtotal'] ?? null;

            return $min === '50.00';
        });
    }

    public function test_no_minimum_purchase_requirement_is_sent_when_not_configured(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '111']);
        $reward = Reward::factory()->for($shop)->percentageDiscount(10)->create(['min_purchase_amount_cents' => null]);
        $this->fakeSuccessfulBasicResponse();

        app(ShopifyDiscountService::class)->createForRedemption($reward, $customer, $this->redemption($shop, $customer, $reward));

        Http::assertSent(function ($request) {
            return ! array_key_exists('minimumRequirement', $request->data()['variables']['basicCodeDiscount']);
        });
    }

    public function test_free_shipping_uses_the_free_shipping_mutation_with_no_customer_gets_fragment(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '111']);
        $reward = Reward::factory()->for($shop)->freeShipping()->create();
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'discountCodeFreeShippingCreate' => [
                        'codeDiscountNode' => ['id' => 'gid://shopify/DiscountCodeNode/2', 'codeDiscount' => ['codes' => ['nodes' => [['code' => 'LOY-SHIP']]]]],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        $result = app(ShopifyDiscountService::class)->createForRedemption($reward, $customer, $this->redemption($shop, $customer, $reward));

        $this->assertSame('LOY-SHIP', $result['discount_code']);
        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($body['query'], 'discountCodeFreeShippingCreate')
                && ! isset($body['variables']['freeShippingCodeDiscount']['customerGets']);
        });
    }

    public function test_a_shopify_user_error_throws_a_discount_creation_failed_exception(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '111']);
        $reward = Reward::factory()->for($shop)->percentageDiscount(10)->create();
        Http::fake([
            '*' => Http::response([
                'data' => ['discountCodeBasicCreate' => ['codeDiscountNode' => null, 'userErrors' => [['field' => ['code'], 'message' => 'Code already taken']]]],
            ], 200),
        ]);

        $this->expectException(DiscountCreationFailedException::class);
        app(ShopifyDiscountService::class)->createForRedemption($reward, $customer, $this->redemption($shop, $customer, $reward));
    }

    public function test_a_missing_discount_id_in_a_technically_successful_response_still_fails_safely(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '111']);
        $reward = Reward::factory()->for($shop)->percentageDiscount(10)->create();
        Http::fake(['*' => Http::response(['data' => ['discountCodeBasicCreate' => ['codeDiscountNode' => null, 'userErrors' => []]]], 200)]);

        $this->expectException(DiscountCreationFailedException::class);
        app(ShopifyDiscountService::class)->createForRedemption($reward, $customer, $this->redemption($shop, $customer, $reward));
    }

    public function test_an_unsupported_reward_type_throws_rather_than_silently_creating_a_wrong_discount(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '111']);
        $reward = Reward::factory()->for($shop)->create(['type' => 'gift']); // no handler registered for 'gift' — see Reward::TYPE_GIFT's docblock

        $this->expectException(DiscountCreationFailedException::class);
        app(ShopifyDiscountService::class)->createForRedemption($reward, $customer, $this->redemption($shop, $customer, $reward));
    }
}
