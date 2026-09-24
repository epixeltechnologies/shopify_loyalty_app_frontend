<?php

namespace App\Services\Referrals;

use App\Exceptions\Referrals\ReferralFraudException;
use App\Models\Customer;
use App\Models\Referral;
use App\Models\Shop;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\Analytics\EventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * REFERRALS: the click → registration attribution pipeline. A
 * `visitor_token` (an opaque, client-generated identifier — e.g. a
 * cookie set by the future storefront widget) is what links an
 * anonymous click to the eventual registered customer; nothing in this
 * flow depends on a Shopify customer existing yet at click time.
 *
 * The registration bridge (`attributeRegistration()`) is a prepared
 * integration point rather than something wired to a live checkout —
 * this app has no storefront-side code yet (see docs/REFERRAL_PROGRAM.md's
 * scope note), so it's invoked either directly by a future storefront
 * call passing the visitor_token it set as a cookie, or by
 * `referred_by_customer_id` being passed straight through customer
 * enrollment when a caller already knows both sides directly.
 */
class ReferralAttributionService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly ReferralSettingsService $settings,
        private readonly ReferralFraudDetectionService $fraud,
        private readonly EventRecorder $events,
    ) {}

    /**
     * Idempotent per visitor_token: a repeat click from the same
     * visitor (browser back button, clicking the link again) returns
     * the existing `clicked` attribution rather than creating a
     * duplicate row or resetting the attribution window — see the
     * task's "prevent referral attribution from being overwritten
     * incorrectly."
     */
    public function recordClick(Shop $shop, string $referralCode, string $visitorToken, ?string $ip = null, ?string $userAgent = null): ?Referral
    {
        $referrer = $this->customers->findByReferralCode($shop, $referralCode);
        if (! $referrer) {
            return null; // unknown code — nothing to attribute
        }

        $existing = Referral::query()
            ->where('shop_id', $shop->id)
            ->where('visitor_token', $visitorToken)
            ->where('status', Referral::STATUS_CLICKED)
            ->first();

        if ($existing) {
            return $existing;
        }

        $windowDays = $this->settings->getOrCreate($shop)->attribution_window_days;

        $referral = Referral::query()->create([
            'shop_id' => $shop->id,
            'referrer_customer_id' => $referrer->id,
            'referral_code' => $referralCode,
            'visitor_token' => $visitorToken,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'status' => Referral::STATUS_CLICKED,
            'clicked_at' => now(),
            'attribution_expires_at' => now()->addDays($windowDays),
        ]);

        $this->events->record($shop, 'referral.created', $referrer, ['referral_id' => $referral->id]);

        return $referral;
    }

    /**
     * @throws ReferralFraudException
     */
    public function attributeRegistration(Shop $shop, string $visitorToken, Customer $referred): ?Referral
    {
        return DB::transaction(function () use ($shop, $visitorToken, $referred) {
            $referral = Referral::query()
                ->where('shop_id', $shop->id)
                ->where('visitor_token', $visitorToken)
                ->where('status', Referral::STATUS_CLICKED)
                ->lockForUpdate()
                ->first();

            if (! $referral || $referral->isAttributionExpired()) {
                return null; // no attribution, or the window has closed — the sale isn't attributed to anyone, which is the correct/safe outcome
            }

            $this->fraud->assertNotFraudulent($referral, $referred);

            $referral->update([
                'referred_customer_id' => $referred->id,
                'status' => Referral::STATUS_REGISTERED,
                'registered_at' => now(),
            ]);

            if (! $referred->referred_by_customer_id) {
                $referred->update(['referred_by_customer_id' => $referral->referrer_customer_id]);
            }

            return $referral->fresh();
        });
    }
}
