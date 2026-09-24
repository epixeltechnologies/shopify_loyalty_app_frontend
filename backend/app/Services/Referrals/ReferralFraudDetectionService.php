<?php

namespace App\Services\Referrals;

use App\Exceptions\Referrals\ReferralFraudException;
use App\Models\Customer;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;

/**
 * REFERRALS: fraud prevention — see docs/REFERRAL_PROGRAM.md's fraud
 * section for the full rationale. Two distinct mechanisms, deliberately
 * not conflated:
 *
 *   - HARD BLOCKS (`assertNotFraudulent()`, throws immediately): checks
 *     that are unambiguous by definition, not just suspicious — a
 *     customer referring themselves, an already-referred customer, a
 *     qualifying order reused across referrals. These reject the
 *     referral outright; no single legitimate scenario produces them.
 *   - SOFT FLAGS (`detectSuspiciousSignals()`, returns reason codes,
 *     never throws): signals that are individually explainable by
 *     coincidence (shared household IP, a fast-but-real signup) and
 *     only become actionable in combination — per the task's explicit
 *     "do not rely only on IP address" and "do not automatically ban
 *     legitimate customers based on one signal," at least TWO
 *     independent signals must be present before a referral is flagged
 *     for merchant review; a single signal is recorded but never
 *     blocks anything on its own.
 */
class ReferralFraudDetectionService
{
    private const FLAG_THRESHOLD = 2;

    private const VELOCITY_WINDOW_MINUTES = 60;

    private const VELOCITY_THRESHOLD = 5;

    private const FAST_REGISTRATION_SECONDS = 3;

    /** @throws ReferralFraudException */
    public function assertNotFraudulent(Referral $referral, Customer $referred): void
    {
        if ($referral->referrer_customer_id === $referred->id) {
            throw new ReferralFraudException('self_referral', 'A customer cannot refer themselves.');
        }

        if ($referral->referrer && $referral->referrer->email && $referral->referrer->email === $referred->email) {
            throw new ReferralFraudException('same_email', 'The referrer and referred customer share the same email address.');
        }

        if ($referred->referred_by_customer_id && $referred->referred_by_customer_id !== $referral->referrer_customer_id) {
            throw new ReferralFraudException('already_referred', 'This customer has already been attributed to a different referral.');
        }

        $duplicateActiveReferral = Referral::query()
            ->where('shop_id', $referral->shop_id)
            ->where('referred_customer_id', $referred->id)
            ->whereNotIn('status', [Referral::STATUS_REJECTED, Referral::STATUS_CANCELLED, Referral::STATUS_EXPIRED])
            ->where('id', '!=', $referral->id)
            ->exists();

        if ($duplicateActiveReferral) {
            throw new ReferralFraudException('duplicate_referral', 'This customer is already attributed to another active referral.');
        }
    }

    /**
     * Runs at qualification time (once a real order and a real
     * referred customer both exist) — returns reason codes for
     * anything suspicious found; the caller (ReferralQualificationService)
     * decides whether the accumulated count crosses the flag threshold.
     *
     * @return string[]
     */
    public function detectSuspiciousSignals(Referral $referral): array
    {
        $signals = [];

        if ($this->sharesIpWithAnotherReferralFromSameReferrer($referral)) {
            $signals[] = 'shared_ip_with_referrer';
        }

        if ($this->referrerVelocityTooHigh($referral)) {
            $signals[] = 'high_referral_velocity';
        }

        if ($this->registeredSuspiciouslyFastAfterClick($referral)) {
            $signals[] = 'fast_registration_after_click';
        }

        return $signals;
    }

    public function shouldFlag(array $signals): bool
    {
        return count($signals) >= self::FLAG_THRESHOLD;
    }

    /** A referred account created from the same IP as another of this referrer's OWN referrals suggests one person operating multiple accounts — not conclusive alone (shared households, offices, coffee shops all produce this), hence a soft signal. */
    private function sharesIpWithAnotherReferralFromSameReferrer(Referral $referral): bool
    {
        if (! $referral->ip_address) {
            return false;
        }

        return Referral::query()
            ->where('shop_id', $referral->shop_id)
            ->where('referrer_customer_id', $referral->referrer_customer_id)
            ->where('id', '!=', $referral->id)
            ->where('ip_address', $referral->ip_address)
            ->exists();
    }

    /** More than a handful of registrations attributed to one referrer within an hour is unusual for organic word-of-mouth referral (though not impossible for a popular creator/influencer, hence soft, not a hard block). */
    private function referrerVelocityTooHigh(Referral $referral): bool
    {
        $count = Referral::query()
            ->where('shop_id', $referral->shop_id)
            ->where('referrer_customer_id', $referral->referrer_customer_id)
            ->where('registered_at', '>=', now()->subMinutes(self::VELOCITY_WINDOW_MINUTES))
            ->whereNotNull('registered_at')
            ->count();

        return $count >= self::VELOCITY_THRESHOLD;
    }

    /** A registration completed within a few seconds of the click is consistent with an automated/scripted signup rather than a human reading a page and filling out a form. */
    private function registeredSuspiciouslyFastAfterClick(Referral $referral): bool
    {
        if (! $referral->clicked_at || ! $referral->registered_at) {
            return false;
        }

        return $referral->clicked_at->diffInSeconds($referral->registered_at) < self::FAST_REGISTRATION_SECONDS;
    }
}
