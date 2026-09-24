<?php

namespace Tests\Unit\Jobs\Referrals;

use App\Jobs\Referrals\ProcessReferralRewardsJob;
use App\Models\Referral;
use App\Models\ReferralSettings;
use App\Models\Shop;
use App\Services\Referrals\ReferralRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessReferralRewardsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_due_qualified_referral_is_rewarded(): void
    {
        $shop = Shop::factory()->create();
        ReferralSettings::factory()->for($shop)->create(['referrer_reward_points' => 300]);
        $referral = Referral::factory()->for($shop)->qualified()->create(['reward_scheduled_at' => now()->subMinute()]);

        (new ProcessReferralRewardsJob($shop))->handle(app(ReferralRewardService::class));

        $this->assertSame('rewarded', $referral->fresh()->status);
    }

    public function test_a_referral_not_yet_due_is_left_alone(): void
    {
        $shop = Shop::factory()->create();
        ReferralSettings::factory()->for($shop)->create(['referrer_reward_points' => 300]);
        $referral = Referral::factory()->for($shop)->qualified()->create(['reward_scheduled_at' => now()->addDays(3)]);

        (new ProcessReferralRewardsJob($shop))->handle(app(ReferralRewardService::class));

        $this->assertSame('qualified', $referral->fresh()->status);
    }

    public function test_a_flagged_referral_is_never_rewarded_even_if_due(): void
    {
        $shop = Shop::factory()->create();
        ReferralSettings::factory()->for($shop)->create(['referrer_reward_points' => 300]);
        $referral = Referral::factory()->for($shop)->qualified()->create([
            'reward_scheduled_at' => now()->subMinute(),
            'fraud_status' => Referral::FRAUD_STATUS_FLAGGED,
        ]);

        (new ProcessReferralRewardsJob($shop))->handle(app(ReferralRewardService::class));

        $this->assertSame('qualified', $referral->fresh()->status); // untouched, awaiting merchant review
    }

    public function test_clearing_fraud_allows_a_previously_flagged_referral_to_be_rewarded(): void
    {
        $shop = Shop::factory()->create();
        ReferralSettings::factory()->for($shop)->create(['referrer_reward_points' => 300]);
        $referral = Referral::factory()->for($shop)->qualified()->create([
            'reward_scheduled_at' => now()->subMinute(),
            'fraud_status' => Referral::FRAUD_STATUS_CLEARED,
        ]);

        (new ProcessReferralRewardsJob($shop))->handle(app(ReferralRewardService::class));

        $this->assertSame('rewarded', $referral->fresh()->status);
    }

    public function test_referrals_from_a_different_shop_are_never_touched(): void
    {
        $shop = Shop::factory()->create();
        $otherShop = Shop::factory()->create();
        ReferralSettings::factory()->for($otherShop)->create(['referrer_reward_points' => 300]);
        $otherShopReferral = Referral::factory()->for($otherShop)->qualified()->create(['reward_scheduled_at' => now()->subMinute()]);

        (new ProcessReferralRewardsJob($shop))->handle(app(ReferralRewardService::class));

        $this->assertSame('qualified', $otherShopReferral->fresh()->status);
    }
}
