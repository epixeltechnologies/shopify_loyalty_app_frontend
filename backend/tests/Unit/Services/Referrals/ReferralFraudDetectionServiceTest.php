<?php

namespace Tests\Unit\Services\Referrals;

use App\Exceptions\Referrals\ReferralFraudException;
use App\Models\Customer;
use App\Models\Referral;
use App\Models\Shop;
use App\Services\Referrals\ReferralFraudDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralFraudDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_cannot_refer_themselves(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $referral = Referral::factory()->for($shop)->create(['referrer_customer_id' => $customer->id]);

        $this->expectException(ReferralFraudException::class);
        app(ReferralFraudDetectionService::class)->assertNotFraudulent($referral, $customer);
    }

    public function test_same_email_between_referrer_and_referred_is_blocked(): void
    {
        $shop = Shop::factory()->create();
        $referrer = Customer::factory()->for($shop)->create(['email' => 'same@example.com']);
        $referred = Customer::factory()->for($shop)->create(['email' => 'same@example.com']);
        $referral = Referral::factory()->for($shop)->create(['referrer_customer_id' => $referrer->id]);

        $this->expectException(ReferralFraudException::class);
        app(ReferralFraudDetectionService::class)->assertNotFraudulent($referral, $referred);
    }

    public function test_an_already_referred_customer_cannot_be_attributed_to_a_different_referrer(): void
    {
        $shop = Shop::factory()->create();
        $originalReferrer = Customer::factory()->for($shop)->create();
        $newReferrer = Customer::factory()->for($shop)->create();
        $referred = Customer::factory()->for($shop)->create(['referred_by_customer_id' => $originalReferrer->id]);
        $referral = Referral::factory()->for($shop)->create(['referrer_customer_id' => $newReferrer->id]);

        $this->expectException(ReferralFraudException::class);
        app(ReferralFraudDetectionService::class)->assertNotFraudulent($referral, $referred);
    }

    public function test_a_duplicate_active_referral_for_the_same_referred_customer_is_blocked(): void
    {
        $shop = Shop::factory()->create();
        $referrer = Customer::factory()->for($shop)->create();
        $referred = Customer::factory()->for($shop)->create();
        Referral::factory()->for($shop)->create(['referrer_customer_id' => $referrer->id, 'referred_customer_id' => $referred->id, 'status' => 'registered']);
        $newAttempt = Referral::factory()->for($shop)->create(['referrer_customer_id' => $referrer->id]);

        $this->expectException(ReferralFraudException::class);
        app(ReferralFraudDetectionService::class)->assertNotFraudulent($newAttempt, $referred);
    }

    public function test_a_rejected_prior_referral_does_not_block_a_new_attempt(): void
    {
        $shop = Shop::factory()->create();
        $referrer = Customer::factory()->for($shop)->create();
        $referred = Customer::factory()->for($shop)->create();
        Referral::factory()->for($shop)->create(['referrer_customer_id' => $referrer->id, 'referred_customer_id' => $referred->id, 'status' => 'rejected']);
        $newAttempt = Referral::factory()->for($shop)->create(['referrer_customer_id' => $referrer->id]);

        app(ReferralFraudDetectionService::class)->assertNotFraudulent($newAttempt, $referred);
        $this->addToAssertionCount(1);
    }

    public function test_a_legitimate_referral_passes_without_exception(): void
    {
        $shop = Shop::factory()->create();
        $referrer = Customer::factory()->for($shop)->create(['email' => 'referrer@example.com']);
        $referred = Customer::factory()->for($shop)->create(['email' => 'referred@example.com']);
        $referral = Referral::factory()->for($shop)->create(['referrer_customer_id' => $referrer->id]);

        app(ReferralFraudDetectionService::class)->assertNotFraudulent($referral, $referred);
        $this->addToAssertionCount(1);
    }

    public function test_a_single_signal_alone_never_crosses_the_flag_threshold(): void
    {
        $service = app(ReferralFraudDetectionService::class);

        $this->assertFalse($service->shouldFlag(['shared_ip_with_referrer']));
    }

    public function test_two_signals_together_cross_the_flag_threshold(): void
    {
        $service = app(ReferralFraudDetectionService::class);

        $this->assertTrue($service->shouldFlag(['shared_ip_with_referrer', 'fast_registration_after_click']));
    }

    public function test_shared_ip_with_another_of_the_same_referrers_referrals_is_detected(): void
    {
        $shop = Shop::factory()->create();
        $referrer = Customer::factory()->for($shop)->create();
        Referral::factory()->for($shop)->create(['referrer_customer_id' => $referrer->id, 'ip_address' => '1.2.3.4']);
        $current = Referral::factory()->for($shop)->create(['referrer_customer_id' => $referrer->id, 'ip_address' => '1.2.3.4']);

        $signals = app(ReferralFraudDetectionService::class)->detectSuspiciousSignals($current);

        $this->assertContains('shared_ip_with_referrer', $signals);
    }

    public function test_fast_registration_after_click_is_detected(): void
    {
        $shop = Shop::factory()->create();
        $referral = Referral::factory()->for($shop)->create([
            'clicked_at' => now()->subSeconds(1),
            'registered_at' => now(),
        ]);

        $signals = app(ReferralFraudDetectionService::class)->detectSuspiciousSignals($referral);

        $this->assertContains('fast_registration_after_click', $signals);
    }

    public function test_normal_paced_registration_is_not_flagged_as_fast(): void
    {
        $shop = Shop::factory()->create();
        $referral = Referral::factory()->for($shop)->create([
            'clicked_at' => now()->subMinutes(10),
            'registered_at' => now(),
        ]);

        $signals = app(ReferralFraudDetectionService::class)->detectSuspiciousSignals($referral);

        $this->assertNotContains('fast_registration_after_click', $signals);
    }

    public function test_high_referral_velocity_is_detected(): void
    {
        $shop = Shop::factory()->create();
        $referrer = Customer::factory()->for($shop)->create();
        Referral::factory()->for($shop)->count(5)->create(['referrer_customer_id' => $referrer->id, 'registered_at' => now()->subMinutes(10)]);
        $current = Referral::factory()->for($shop)->create(['referrer_customer_id' => $referrer->id, 'registered_at' => now()]);

        $signals = app(ReferralFraudDetectionService::class)->detectSuspiciousSignals($current);

        $this->assertContains('high_referral_velocity', $signals);
    }
}
