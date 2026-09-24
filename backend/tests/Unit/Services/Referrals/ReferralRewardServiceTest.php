<?php

namespace Tests\Unit\Services\Referrals;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\ReferralSettings;
use App\Models\Shop;
use App\Services\Referrals\ReferralRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralRewardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewarding_a_qualified_referral_pays_both_referrer_and_referee(): void
    {
        $shop = Shop::factory()->create();
        ReferralSettings::factory()->for($shop)->create(['referrer_reward_points' => 500, 'referee_reward_points' => 250]);
        $referrer = Customer::factory()->for($shop)->create();
        $referred = Customer::factory()->for($shop)->create();
        $referral = Referral::factory()->for($shop)->qualified()->create([
            'referrer_customer_id' => $referrer->id, 'referred_customer_id' => $referred->id,
        ]);

        $result = app(ReferralRewardService::class)->reward($referral);

        $this->assertTrue($result);
        $this->assertSame('rewarded', $referral->fresh()->status);
        $this->assertSame(500, $referrer->point->fresh()->balance);
        $this->assertSame(250, $referred->point->fresh()->balance);
        $this->assertDatabaseHas('point_transactions', ['customer_id' => $referrer->id, 'source' => PointTransaction::SOURCE_REFERRAL_REWARD]);
        $this->assertDatabaseHas('point_transactions', ['customer_id' => $referred->id, 'source' => PointTransaction::SOURCE_REFERRAL_BONUS]);
        $this->assertDatabaseHas('referral_rewards', ['referral_id' => $referral->id, 'beneficiary' => 'referrer', 'points_awarded' => 500]);
        $this->assertDatabaseHas('referral_rewards', ['referral_id' => $referral->id, 'beneficiary' => 'referee', 'points_awarded' => 250]);
    }

    public function test_a_referee_only_program_never_pays_the_referrer(): void
    {
        $shop = Shop::factory()->create();
        ReferralSettings::factory()->for($shop)->create(['referrer_reward_points' => null, 'referee_reward_points' => 250]);
        $referrer = Customer::factory()->for($shop)->create();
        $referred = Customer::factory()->for($shop)->create();
        $referral = Referral::factory()->for($shop)->qualified()->create([
            'referrer_customer_id' => $referrer->id, 'referred_customer_id' => $referred->id,
        ]);

        app(ReferralRewardService::class)->reward($referral);

        $this->assertSame(0, $referrer->point->fresh()->balance);
        $this->assertSame(250, $referred->point->fresh()->balance);
    }

    public function test_rewarding_twice_never_double_pays(): void
    {
        $shop = Shop::factory()->create();
        ReferralSettings::factory()->for($shop)->create(['referrer_reward_points' => 500, 'referee_reward_points' => null]);
        $referrer = Customer::factory()->for($shop)->create();
        $referred = Customer::factory()->for($shop)->create();
        $referral = Referral::factory()->for($shop)->qualified()->create([
            'referrer_customer_id' => $referrer->id, 'referred_customer_id' => $referred->id,
        ]);
        $service = app(ReferralRewardService::class);

        $first = $service->reward($referral);
        $second = $service->reward($referral->fresh()); // already rewarded now — status guard should block this

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertSame(500, $referrer->point->fresh()->balance); // not 1000
        $this->assertSame(1, \App\Models\ReferralReward::query()->count());
    }

    public function test_a_referral_with_no_configured_rewards_completes_with_nothing_granted(): void
    {
        $shop = Shop::factory()->create();
        ReferralSettings::factory()->for($shop)->create(['referrer_reward_points' => null, 'referee_reward_points' => null]);
        $referral = Referral::factory()->for($shop)->qualified()->create();

        $result = app(ReferralRewardService::class)->reward($referral);

        $this->assertFalse($result);
        $this->assertSame('rewarded', $referral->fresh()->status); // still transitions — nothing configured isn't an error
    }
}
