<?php

namespace Tests\Unit\Services\Referrals;

use App\Exceptions\Referrals\ReferralFraudException;
use App\Models\Customer;
use App\Models\Referral;
use App\Models\ReferralSettings;
use App\Models\Shop;
use App\Services\Referrals\ReferralAttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralAttributionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_a_click_creates_a_clicked_referral(): void
    {
        $shop = Shop::factory()->create();
        $referrer = Customer::factory()->for($shop)->create();

        $referral = app(ReferralAttributionService::class)->recordClick($shop, $referrer->referral_code, 'visitor-abc', '1.2.3.4', 'Mozilla/5.0');

        $this->assertSame('clicked', $referral->status);
        $this->assertSame($referrer->id, $referral->referrer_customer_id);
        $this->assertSame('1.2.3.4', $referral->ip_address);
        $this->assertNotNull($referral->attribution_expires_at);
    }

    public function test_an_unknown_referral_code_records_nothing(): void
    {
        $shop = Shop::factory()->create();

        $referral = app(ReferralAttributionService::class)->recordClick($shop, 'NOTAREALCODE', 'visitor-abc');

        $this->assertNull($referral);
        $this->assertSame(0, Referral::query()->count());
    }

    public function test_a_repeat_click_from_the_same_visitor_returns_the_existing_referral(): void
    {
        $shop = Shop::factory()->create();
        $referrer = Customer::factory()->for($shop)->create();
        $service = app(ReferralAttributionService::class);

        $first = $service->recordClick($shop, $referrer->referral_code, 'visitor-abc');
        $second = $service->recordClick($shop, $referrer->referral_code, 'visitor-abc');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Referral::query()->count());
    }

    public function test_attribution_window_uses_the_shops_configured_setting(): void
    {
        $shop = Shop::factory()->create();
        ReferralSettings::factory()->for($shop)->create(['attribution_window_days' => 7]);
        $referrer = Customer::factory()->for($shop)->create();

        $referral = app(ReferralAttributionService::class)->recordClick($shop, $referrer->referral_code, 'visitor-abc');

        $this->assertTrue($referral->attribution_expires_at->diffInDays(now()->addDays(7)) < 1);
    }

    public function test_registration_within_the_attribution_window_completes_attribution(): void
    {
        $shop = Shop::factory()->create();
        $referrer = Customer::factory()->for($shop)->create(['email' => 'referrer@x.com']);
        app(ReferralAttributionService::class)->recordClick($shop, $referrer->referral_code, 'visitor-abc');
        $newCustomer = Customer::factory()->for($shop)->create(['email' => 'newbie@x.com']);

        $referral = app(ReferralAttributionService::class)->attributeRegistration($shop, 'visitor-abc', $newCustomer);

        $this->assertSame('registered', $referral->status);
        $this->assertSame($newCustomer->id, $referral->referred_customer_id);
        $this->assertSame($referrer->id, $newCustomer->fresh()->referred_by_customer_id);
    }

    public function test_registration_after_the_attribution_window_closes_attributes_nothing(): void
    {
        $shop = Shop::factory()->create();
        $referrer = Customer::factory()->for($shop)->create();
        $referral = Referral::factory()->for($shop)->clicked()->create([
            'referrer_customer_id' => $referrer->id,
            'visitor_token' => 'expired-visitor',
            'attribution_expires_at' => now()->subDay(),
        ]);
        $newCustomer = Customer::factory()->for($shop)->create();

        $result = app(ReferralAttributionService::class)->attributeRegistration($shop, 'expired-visitor', $newCustomer);

        $this->assertNull($result);
        $this->assertNull($newCustomer->fresh()->referred_by_customer_id);
    }

    public function test_registration_with_an_unknown_visitor_token_attributes_nothing(): void
    {
        $shop = Shop::factory()->create();
        $newCustomer = Customer::factory()->for($shop)->create();

        $result = app(ReferralAttributionService::class)->attributeRegistration($shop, 'never-clicked', $newCustomer);

        $this->assertNull($result);
    }

    public function test_self_referral_is_rejected_at_registration_time(): void
    {
        $shop = Shop::factory()->create();
        $referrer = Customer::factory()->for($shop)->create();
        app(ReferralAttributionService::class)->recordClick($shop, $referrer->referral_code, 'visitor-self');

        $this->expectException(ReferralFraudException::class);
        app(ReferralAttributionService::class)->attributeRegistration($shop, 'visitor-self', $referrer);
    }
}
