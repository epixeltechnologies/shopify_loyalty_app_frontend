<?php

namespace App\Jobs\Rewards;

use App\Exceptions\Rewards\DiscountCreationFailedException;
use App\Jobs\Concerns\HasDefaultRetryPolicy;
use App\Models\PointTransaction;
use App\Models\RewardRedemption;
use App\Services\Analytics\EventRecorder;
use App\Services\Notifications\NotificationService;
use App\Services\Points\PointsLedgerService;
use App\Services\Rewards\ShopifyDiscountService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs AFTER `RewardRedemptionService::redeem()`'s transaction has
 * already committed points-deducted, `pending` redemption. This is
 * where the actual Shopify API call happens — deliberately outside any
 * lock/transaction from the redemption request itself (see that
 * service's docblock).
 *
 * Two outcomes:
 *   - Success: the redemption moves to `completed` with the discount
 *     ID/code stored — see `docs/REWARDS_ENGINE.md`.
 *   - Failure (including exhausting `HasDefaultRetryPolicy`'s bounded
 *     retries — a transient Shopify error is worth retrying a few times
 *     before giving up): the ALREADY-DEDUCTED points are refunded via a
 *     compensating ledger entry (never by mutating the original
 *     `redeem` transaction), and the redemption moves to `failed` with
 *     a reason. This is what "if discount creation fails, do not
 *     permanently deduct points" means in an architecture where the
 *     Shopify call happens in a separate queued job/transaction from
 *     the point deduction: the guarantee is upheld by a compensating
 *     action, not a literal single-transaction rollback, which isn't
 *     possible once the first transaction has already committed and
 *     this job is running in an entirely separate process.
 */
class FulfillRewardRedemptionJob implements ShouldQueue
{
    use Dispatchable, HasDefaultRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly RewardRedemption $redemption) {}

    public function handle(ShopifyDiscountService $discounts): void
    {
        // Idempotent against a retried/duplicate job run for the same
        // redemption — a redemption that already reached a terminal
        // state should never be processed twice.
        if ($this->redemption->status !== RewardRedemption::STATUS_PENDING) {
            return;
        }

        $redemption = $this->redemption->fresh(['reward', 'customer']);

        try {
            $result = $discounts->createForRedemption($redemption->reward, $redemption->customer, $redemption);

            $redemption->update([
                'status' => RewardRedemption::STATUS_COMPLETED,
                'shopify_discount_id' => $result['discount_id'],
                'shopify_discount_code' => $result['discount_code'],
                'fulfilled_at' => now(),
            ]);

            app(EventRecorder::class)->record($redemption->customer->shop, 'reward.redeemed', $redemption->customer, [
                'reward_id' => $redemption->reward_id,
                'points_spent' => $redemption->points_spent,
            ]);

            app(NotificationService::class)->notify($redemption->customer->shop, $redemption->customer, 'reward_redeemed', [
                'reward_name' => $redemption->reward->name,
                'discount_code' => $result['discount_code'],
            ]);
        } catch (DiscountCreationFailedException $e) {
            $this->recordFailureAndRefund($redemption, $e->getMessage());
        }
    }

    /**
     * Called both when discount creation raises immediately AND when
     * every queue retry has been exhausted (`failed()` below) — the
     * same compensating action either way, so points are never left
     * permanently deducted for a redemption that never actually
     * produced a usable reward.
     */
    private function recordFailureAndRefund(RewardRedemption $redemption, string $reason): void
    {
        DB::transaction(function () use ($redemption, $reason) {
            $redemption = RewardRedemption::query()->where('id', $redemption->id)->lockForUpdate()->firstOrFail();

            if ($redemption->status !== RewardRedemption::STATUS_PENDING) {
                return; // already handled by a prior attempt — idempotent
            }

            app(PointsLedgerService::class)->post(
                customer: $redemption->customer,
                direction: PointTransaction::DIRECTION_ADJUST,
                points: $redemption->points_spent,
                source: PointTransaction::SOURCE_REDEMPTION_REFUND,
                sourceReferenceType: RewardRedemption::class,
                sourceReferenceId: $redemption->id,
                note: "Refund: reward redemption failed ({$redemption->reward?->name}).",
                idempotencyKey: "redemption_refund:{$redemption->shop_id}:{$redemption->id}",
            );

            $redemption->update(['status' => RewardRedemption::STATUS_FAILED, 'failure_reason' => $reason]);
        });

        Log::warning('Reward redemption failed and was refunded', ['redemption_id' => $redemption->id, 'reason' => $reason]);
    }

    /** Called by the queue worker once retries are exhausted (see HasDefaultRetryPolicy) — the final backstop that guarantees a permanently-failing Shopify call still refunds points rather than leaving the redemption stuck in `pending` forever. */
    public function failed(\Throwable $e): void
    {
        $redemption = $this->redemption->fresh();

        if ($redemption && $redemption->status === RewardRedemption::STATUS_PENDING) {
            $this->recordFailureAndRefund($redemption, $e->getMessage());
        }
    }
}
