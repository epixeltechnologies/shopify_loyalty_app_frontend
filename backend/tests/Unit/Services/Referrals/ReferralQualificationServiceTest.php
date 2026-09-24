<?php

namespace Tests\Unit\Services\Referrals;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\ReferralSettings;
use App\Models\Shop;
use App\Services\Points\PointsLedgerService;
use App\Services\Referrals\ReferralQualificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralQualificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setup(array $settingsOverrides = []): array
    {
        $shop = Shop::factory()->create();
        ReferralSettings::factory()->for($shop)->create(array_merge(['enabled' => true], $settingsOverrides));
        $referrer = Customer::factory()->for($shop)->create();
        $referred = Customer::factory()->for($shop)->create();
        $referral = Referral::factory()->for($shop)->create([
            'referrer_customer_id' => $referrer->id,
            'referred_customer_id' => $referred->id,
            'status' => 'registered',
            'registered_at' => now()->subHour(),
            'attribution_expires_at' => now()->addDays(30),
        ]);

        return [$shop, $referrer, $referred, $referral];
    }

    public function test_a_qualifying_order_moves_the_referral_to_qualified(): void
    {
        [$shop, , $referred, $referral] = $this->setup();
        $order = Order::factory()->for($shop)->for($referred)->create(['financial_status' => 'paid', 'total_cents' => 5000]);

        app(ReferralQualificationService::class)->evaluateOrder($order);

        $this->assertSame('qualified', $referral->fresh()->status);
        $this->assertSame((string) $order->shopify_order_id, $referral->fresh()->qualifying_order_id);
    }

    public function test_an_order_below_the_minimum_does_not_qualify(): void
    {
        [$shop, , $referred, $referral] = $this->setup(['minimum_qualifying_order_cents' => 10000]);
        $order = Order::factory()->for($shop)->for($referred)->create(['financial_status' => 'paid', 'total_cents' => 5000]);

        app(ReferralQualificationService::class)->evaluateOrder($order);

        $this->assertSame('registered', $referral->fresh()->status);
    }

    public function test_an_unpaid_order_does_not_qualify(): void
    {
        [$shop, , $referred, $referral] = $this->setup();
        $order = Order::factory()->for($shop)->for($referred)->create(['financial_status' => 'pending', 'total_cents' => 5000]);

        app(ReferralQualificationService::class)->evaluateOrder($order);

        $this->assertSame('registered', $referral->fresh()->status);
    }

    public function test_require_first_purchase_blocks_a_second_order(): void
    {
        [$shop, , $referred, $referral] = $this->setup(['require_first_purchase' => true]);
        Order::factory()->for($shop)->for($referred)->create(['financial_status' => 'paid', 'total_cents' => 1000]);
        $secondOrder = Order::factory()->for($shop)->for($referred)->create(['financial_status' => 'paid', 'total_cents' => 1000]);

        app(ReferralQualificationService::class)->evaluateOrder($secondOrder);

        $this->assertSame('registered', $referral->fresh()->status);
    }

    public function test_disabling_the_program_prevents_qualification(): void
    {
        [$shop, , $referred, $referral] = $this->setup(['enabled' => false]);
        $order = Order::factory()->for($shop)->for($referred)->create(['financial_status' => 'paid', 'total_cents' => 5000]);

        app(ReferralQualificationService::class)->evaluateOrder($order);

        $this->assertSame('registered', $referral->fresh()->status);
    }

    public function test_max_referrals_per_customer_blocks_qualification_once_reached(): void
    {
        [$shop, $referrer, $referred, $referral] = $this->setup(['max_referrals_per_customer' => 1]);
        // The referrer already has one other successful referral.
        Referral::factory()->for($shop)->create(['referrer_customer_id' => $referrer->id, 'status' => 'rewarded']);
        $order = Order::factory()->for($shop)->for($referred)->create(['financial_status' => 'paid', 'total_cents' => 5000]);

        app(ReferralQualificationService::class)->evaluateOrder($order);

        $this->assertSame('registered', $referral->fresh()->status);
    }

    public function test_reward_scheduled_at_respects_the_configured_delay(): void
    {
        [$shop, , $referred, $referral] = $this->setup(['reward_delay_days' => 5]);
        $order = Order::factory()->for($shop)->for($referred)->create(['financial_status' => 'paid', 'total_cents' => 5000]);

        app(ReferralQualificationService::class)->evaluateOrder($order);

        $scheduledAt = $referral->fresh()->reward_scheduled_at;
        $this->assertTrue($scheduledAt->diffInDays(now()->addDays(5)) < 1);
    }

    public function test_cancelling_a_qualified_but_not_yet_rewarded_referrals_order_rejects_it(): void
    {
        [$shop, , $referred, $referral] = $this->setup();
        $order = Order::factory()->for($shop)->for($referred)->create(['financial_status' => 'paid', 'total_cents' => 5000]);
        app(ReferralQualificationService::class)->evaluateOrder($order);

        app(ReferralQualificationService::class)->handleQualifyingOrderVoided($order, 'Order cancelled');

        $this->assertSame('rejected', $referral->fresh()->status);
    }

    public function test_refunding_an_already_rewarded_referral_claws_back_points(): void
    {
        [$shop, $referrer, $referred, $referral] = $this->setup();
        $order = Order::factory()->for($shop)->for($referred)->create(['financial_status' => 'paid', 'total_cents' => 5000]);
        app(ReferralQualificationService::class)->evaluateOrder($order);

        app(PointsLedgerService::class)->post($referrer, PointTransaction::DIRECTION_EARN, 500, PointTransaction::SOURCE_REFERRAL_REWARD);
        $reward = ReferralReward::factory()->for($shop)->create([
            'referral_id' => $referral->id, 'customer_id' => $referrer->id, 'beneficiary' => 'referrer',
            'points_awarded' => 500, 'granted_at' => now(),
        ]);
        $referral->update(['status' => 'rewarded', 'rewarded_at' => now()]);

        app(ReferralQualificationService::class)->handleQualifyingOrderVoided($order, 'Order fully refunded');

        $this->assertSame('rejected', $referral->fresh()->status);
        $this->assertSame(0, $referrer->point->fresh()->balance); // 500 earned, 500 clawed back
        $this->assertSoftDeleted($reward);
    }
}
