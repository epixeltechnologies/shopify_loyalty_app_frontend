<?php

namespace App\Services\Referrals;

use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\ReferralSettings;
use App\Services\Analytics\EventRecorder;
use App\Services\Points\PointAdjustmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * REFERRALS: integrates the referral lifecycle with the order/refund
 * sync pipeline (see docs/SYNCHRONIZATION.md) — this is the ONLY class
 * that decides whether an order qualifies a referral; webhook jobs
 * call it, they never contain this logic themselves (same pattern as
 * PointsAccrualService for purchase points).
 */
class ReferralQualificationService
{
    public function __construct(
        private readonly ReferralSettingsService $settingsService,
        private readonly ReferralFraudDetectionService $fraud,
        private readonly PointAdjustmentService $adjustments,
        private readonly EventRecorder $events,
    ) {}

    /**
     * Called after order sync for every paid order — a no-op unless
     * the order's customer is the `referred_customer_id` on a
     * `registered` referral still within its attribution window. Never
     * throws for an ordinary "this order doesn't qualify anything"
     * case (that's the overwhelmingly common path); fraud exceptions
     * are caught and turned into a soft flag rather than propagated,
     * since a hard-block fraud finding at qualification time should
     * pause the reward for review, not crash order processing.
     */
    public function evaluateOrder(Order $order): void
    {
        if (! $order->customer_id || $order->financial_status !== 'paid') {
            return;
        }

        $referral = Referral::query()
            ->where('shop_id', $order->shop_id)
            ->where('referred_customer_id', $order->customer_id)
            ->where('status', Referral::STATUS_REGISTERED)
            ->first();

        if (! $referral || $referral->isAttributionExpired()) {
            return;
        }

        $settings = $this->settingsService->getOrCreate($order->shop);
        if (! $settings->enabled) {
            return;
        }

        if (! $this->meetsQualifyingRequirements($order, $settings)) {
            return; // stays `registered` — a later, larger order can still qualify it before the window closes
        }

        if ($this->referrerHasReachedMaxReferrals($referral, $settings)) {
            return; // this referrer has already hit their configured cap — stays `registered`, never qualifies further
        }

        $wasQualifiedNow = DB::transaction(function () use ($referral, $order, $settings) {
            $locked = Referral::query()->where('id', $referral->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Referral::STATUS_REGISTERED) {
                return false; // already qualified/rejected by a concurrent attempt — idempotent
            }

            $locked->update([
                'status' => Referral::STATUS_QUALIFIED,
                'qualifying_order_id' => (string) $order->shopify_order_id,
                'qualifying_order_value_cents' => $order->total_cents,
                'qualified_at' => now(),
                'reward_scheduled_at' => now()->addDays($settings->reward_delay_days),
            ]);

            $signals = $this->fraud->detectSuspiciousSignals($locked);
            if (! empty($signals)) {
                $locked->update([
                    'fraud_status' => $this->fraud->shouldFlag($signals) ? Referral::FRAUD_STATUS_FLAGGED : Referral::FRAUD_STATUS_NONE,
                    'fraud_reasons' => $signals,
                ]);
            }

            return true;
        });

        // Recorded only on the transition itself, outside the lock —
        // never on the idempotent no-op path, which would otherwise
        // double-count this referral in "referrals qualified" reporting
        // if this method is ever invoked twice for the same order
        // (a retried job, a redelivered webhook).
        if ($wasQualifiedNow) {
            $this->events->record($order->shop, 'referral.qualified', $referral->referrer, ['referral_id' => $referral->id]);
        }
    }

    private function referrerHasReachedMaxReferrals(Referral $referral, ReferralSettings $settings): bool
    {
        if (! $settings->max_referrals_per_customer) {
            return false;
        }

        $successfulCount = Referral::query()
            ->where('shop_id', $referral->shop_id)
            ->where('referrer_customer_id', $referral->referrer_customer_id)
            ->whereIn('status', [Referral::STATUS_QUALIFIED, Referral::STATUS_REWARDED])
            ->where('id', '!=', $referral->id)
            ->count();

        return $successfulCount >= $settings->max_referrals_per_customer;
    }

    private function meetsQualifyingRequirements(Order $order, ReferralSettings $settings): bool
    {
        if ($settings->minimum_qualifying_order_cents && $order->total_cents < $settings->minimum_qualifying_order_cents) {
            return false;
        }

        if ($settings->require_first_purchase) {
            $priorPaidOrders = Order::query()
                ->where('shop_id', $order->shop_id)
                ->where('customer_id', $order->customer_id)
                ->where('financial_status', 'paid')
                ->where('id', '!=', $order->id)
                ->exists();

            if ($priorPaidOrders) {
                return false;
            }
        }

        return true;
    }

    /**
     * Called from `HandleOrderCancelledJob`/`HandleRefundCreatedJob`
     * once a referral's qualifying order is fully cancelled or
     * refunded — this is exactly the scenario the configurable reward
     * DELAY exists to reduce the frequency of (see
     * docs/REFERRAL_PROGRAM.md), but can't eliminate entirely (a
     * refund can still happen after the delay has elapsed and the
     * reward already paid out).
     *
     *   - Not yet rewarded (`qualified`, still waiting out the delay):
     *     simply reject the referral — it never pays out.
     *   - Already `rewarded`: claws back the points via a compensating
     *     ledger entry (PointAdjustmentService::correct(), a system-
     *     initiated correction, not a merchant action) and voids the
     *     `ReferralReward` row (soft-deleted, matching that table's own
     *     "can be voided" migration comment) rather than deleting the
     *     historical record outright.
     */
    public function handleQualifyingOrderVoided(Order $order, string $reason): void
    {
        $referral = Referral::query()
            ->where('shop_id', $order->shop_id)
            ->where('qualifying_order_id', (string) $order->shopify_order_id)
            ->whereIn('status', [Referral::STATUS_QUALIFIED, Referral::STATUS_REWARDED])
            ->first();

        if (! $referral) {
            return;
        }

        DB::transaction(function () use ($referral, $reason) {
            $locked = Referral::query()->where('id', $referral->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Referral::STATUS_QUALIFIED) {
                $locked->update(['status' => Referral::STATUS_REJECTED, 'rejected_at' => now(), 'rejection_reason' => $reason]);

                return;
            }

            if ($locked->status === Referral::STATUS_REWARDED) {
                $this->clawBackRewards($locked, $reason);
                $locked->update(['status' => Referral::STATUS_REJECTED, 'rejected_at' => now(), 'rejection_reason' => $reason]);
            }
        });
    }

    private function clawBackRewards(Referral $referral, string $reason): void
    {
        foreach ($referral->rewards()->whereNotNull('granted_at')->get() as $reward) {
            if (! $reward->points_awarded) {
                continue;
            }

            try {
                $this->adjustments->correct(
                    shop: $referral->shop,
                    customer: $reward->customer,
                    points: -$reward->points_awarded,
                    reason: "Referral reward clawed back: {$reason}",
                );
            } catch (\Throwable $e) {
                // A customer who already spent the clawed-back points
                // (balance would go negative and this shop disallows
                // it) is a real, expected outcome — log and leave the
                // reward record in place rather than letting one
                // customer's insufficient balance abort the whole
                // cancellation/refund webhook.
                Log::warning('Referral reward clawback could not be fully applied', ['referral_id' => $referral->id, 'reward_id' => $reward->id, 'error' => $e->getMessage()]);
            }

            $reward->delete(); // soft-delete — voided, not erased
        }
    }
}
