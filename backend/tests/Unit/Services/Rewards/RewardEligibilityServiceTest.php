<?php

namespace Tests\Unit\Services\Rewards;

use App\Exceptions\Rewards\CustomerNotEligibleException;
use App\Exceptions\Rewards\RewardNotRedeemableException;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Shop;
use App\Models\VipTier;
use App\Services\Rewards\RewardEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_reward_within_its_window_is_redeemable(): void
    {
        $reward = Reward::factory()->create(['status' => 'active']);

        app(RewardEligibilityService::class)->assertRedeemable($reward);
        $this->addToAssertionCount(1); // no exception thrown
    }

    public function test_a_draft_reward_is_not_redeemable(): void
    {
        $reward = Reward::factory()->create(['status' => 'draft']);

        $this->expectException(RewardNotRedeemableException::class);
        app(RewardEligibilityService::class)->assertRedeemable($reward);
    }

    public function test_a_reward_before_its_start_date_is_not_redeemable(): void
    {
        $reward = Reward::factory()->create(['status' => 'active', 'starts_at' => now()->addDay()]);

        $this->expectException(RewardNotRedeemableException::class);
        app(RewardEligibilityService::class)->assertRedeemable($reward);
    }

    public function test_a_reward_past_its_end_date_is_not_redeemable(): void
    {
        $reward = Reward::factory()->create(['status' => 'active', 'ends_at' => now()->subDay()]);

        $this->expectException(RewardNotRedeemableException::class);
        app(RewardEligibilityService::class)->assertRedeemable($reward);
    }

    public function test_a_reward_at_its_stock_limit_is_not_redeemable(): void
    {
        $shop = Shop::factory()->create();
        $reward = Reward::factory()->for($shop)->create(['status' => 'active', 'stock_limit' => 1]);
        RewardRedemption::factory()->for($shop)->for($reward)->create(['status' => 'completed']);

        $this->expectException(RewardNotRedeemableException::class);
        app(RewardEligibilityService::class)->assertRedeemable($reward);
    }

    public function test_a_cancelled_redemption_does_not_count_against_stock(): void
    {
        $shop = Shop::factory()->create();
        $reward = Reward::factory()->for($shop)->create(['status' => 'active', 'stock_limit' => 1]);
        RewardRedemption::factory()->for($shop)->for($reward)->create(['status' => 'cancelled']);

        app(RewardEligibilityService::class)->assertRedeemable($reward);
        $this->addToAssertionCount(1);
    }

    public function test_a_failed_redemption_does_not_count_against_stock(): void
    {
        $shop = Shop::factory()->create();
        $reward = Reward::factory()->for($shop)->create(['status' => 'active', 'stock_limit' => 1]);
        RewardRedemption::factory()->for($shop)->for($reward)->create(['status' => 'failed']);

        app(RewardEligibilityService::class)->assertRedeemable($reward);
        $this->addToAssertionCount(1);
    }

    public function test_max_total_redemptions_blocks_once_reached(): void
    {
        $shop = Shop::factory()->create();
        $reward = Reward::factory()->for($shop)->create(['status' => 'active', 'max_total_redemptions' => 2]);
        RewardRedemption::factory()->for($shop)->for($reward)->count(2)->create(['status' => 'completed']);

        $this->expectException(RewardNotRedeemableException::class);
        app(RewardEligibilityService::class)->assertRedeemable($reward);
    }

    public function test_customer_limit_blocks_when_reached(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $reward = Reward::factory()->for($shop)->create(['max_redemptions_per_customer' => 1]);
        RewardRedemption::factory()->for($shop)->for($reward)->for($customer)->create(['status' => 'completed']);

        $this->expectException(RewardNotRedeemableException::class);
        app(RewardEligibilityService::class)->assertWithinCustomerLimit($reward, $customer);
    }

    public function test_customer_limit_does_not_block_a_different_customer(): void
    {
        $shop = Shop::factory()->create();
        $customerA = Customer::factory()->for($shop)->create();
        $customerB = Customer::factory()->for($shop)->create();
        $reward = Reward::factory()->for($shop)->create(['max_redemptions_per_customer' => 1]);
        RewardRedemption::factory()->for($shop)->for($reward)->for($customerA)->create(['status' => 'completed']);

        app(RewardEligibilityService::class)->assertWithinCustomerLimit($reward, $customerB);
        $this->addToAssertionCount(1);
    }

    public function test_no_eligibility_rule_allows_any_customer(): void
    {
        $customer = Customer::factory()->create();
        $reward = Reward::factory()->for($customer->shop)->create(['customer_eligibility' => null]);

        app(RewardEligibilityService::class)->assertCustomerEligible($reward, $customer);
        $this->addToAssertionCount(1);
    }

    public function test_vip_tier_minimum_blocks_a_customer_below_the_required_tier(): void
    {
        $shop = Shop::factory()->create();
        $silver = VipTier::factory()->for($shop)->create(['slug' => 'silver', 'sort_order' => 1]);
        $gold = VipTier::factory()->for($shop)->create(['slug' => 'gold', 'sort_order' => 2]);
        $customer = Customer::factory()->for($shop)->create(['vip_tier_id' => $silver->id]);
        $reward = Reward::factory()->for($shop)->create(['customer_eligibility' => ['type' => 'vip_tier_minimum', 'vip_tier_id' => $gold->id]]);

        $this->expectException(CustomerNotEligibleException::class);
        app(RewardEligibilityService::class)->assertCustomerEligible($reward, $customer);
    }

    public function test_vip_tier_minimum_allows_a_customer_at_or_above_the_required_tier(): void
    {
        $shop = Shop::factory()->create();
        $gold = VipTier::factory()->for($shop)->create(['slug' => 'gold', 'sort_order' => 2]);
        $customer = Customer::factory()->for($shop)->create(['vip_tier_id' => $gold->id]);
        $reward = Reward::factory()->for($shop)->create(['customer_eligibility' => ['type' => 'vip_tier_minimum', 'vip_tier_id' => $gold->id]]);

        app(RewardEligibilityService::class)->assertCustomerEligible($reward, $customer);
        $this->addToAssertionCount(1);
    }

    public function test_specific_customers_eligibility_blocks_a_customer_not_on_the_list(): void
    {
        $shop = Shop::factory()->create();
        $allowed = Customer::factory()->for($shop)->create();
        $notAllowed = Customer::factory()->for($shop)->create();
        $reward = Reward::factory()->for($shop)->create(['customer_eligibility' => ['type' => 'specific_customers', 'customer_ids' => [$allowed->id]]]);

        $this->expectException(CustomerNotEligibleException::class);
        app(RewardEligibilityService::class)->assertCustomerEligible($reward, $notAllowed);
    }
}
