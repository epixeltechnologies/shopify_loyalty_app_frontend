<?php

namespace App\Services\Points;

use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

/**
 * POINT SYSTEM: reverses previously-posted points for a cancelled order
 * or a refund — always as a NEW compensating `adjust` ledger entry,
 * never by mutating the original `earn` row (the ledger's core
 * immutability guarantee — see docs/ARCHITECTURE.md#loyalty-domain).
 *
 * Both reversal paths share one core rule: never reverse more than was
 * originally earned for the order, and never reverse the same amount
 * twice — enforced by summing what's ALREADY been reversed (via prior
 * `refund_reversal`/`cancellation_reversal` transactions referencing
 * the same order) before computing what's still owed, not by trusting
 * the caller to only call this once.
 */
class PointReversalService
{
    public function __construct(private readonly PointsLedgerService $ledger) {}

    /**
     * Full reversal — an order cancellation reverses everything earned
     * for it that hasn't already been reversed (e.g. by an earlier
     * partial refund on the same order before it was fully cancelled).
     * Idempotent via `cancellation_reversal:{shop_id}:{order_id}` —
     * calling this twice for the same order (a retried job, a
     * redelivered `orders/cancelled` webhook) is a safe no-op the
     * second time.
     */
    public function reverseForCancellation(Order $order): ?PointTransaction
    {
        if (! $order->customer_id) {
            return null; // no customer was ever associated — nothing to reverse
        }

        return DB::transaction(function () use ($order) {
            $earned = $this->totalEarnedForOrder($order);
            $alreadyReversed = $this->totalReversedForOrder($order);
            $remaining = $earned - $alreadyReversed;

            if ($remaining <= 0) {
                return null; // nothing left to reverse (or nothing was ever earned)
            }

            return $this->ledger->post(
                customer: $order->customer,
                direction: PointTransaction::DIRECTION_ADJUST,
                points: -$remaining,
                source: PointTransaction::SOURCE_CANCELLATION_REVERSAL,
                sourceReferenceType: Order::class,
                sourceReferenceId: $order->id,
                note: "Order #{$order->order_number} was cancelled — {$remaining} points reversed.",
                idempotencyKey: "cancellation_reversal:{$order->shop_id}:{$order->id}",
            );
        });
    }

    /**
     * Proportional reversal — a refund reverses points in proportion to
     * how much of the order's value was refunded (refund amount /
     * order total), capped at whatever hasn't already been reversed by
     * a prior refund on the SAME order — this is what makes multiple
     * partial refunds on one order accurate in aggregate rather than
     * each one independently reversing the full proportional share.
     * Idempotent via `refund_reversal:{shop_id}:{refund_id}` — a
     * specific refund's reversal is posted at most once, regardless of
     * webhook redelivery.
     */
    public function reverseForRefund(Refund $refund): ?PointTransaction
    {
        $order = $refund->order;

        if (! $order || ! $order->customer_id || $order->total_cents <= 0) {
            return null;
        }

        return DB::transaction(function () use ($refund, $order) {
            $earned = $this->totalEarnedForOrder($order);
            if ($earned <= 0) {
                return null;
            }

            $alreadyReversed = $this->totalReversedForOrder($order);
            $remainingReversible = $earned - $alreadyReversed;
            if ($remainingReversible <= 0) {
                return null;
            }

            $refundProportion = min(1.0, $refund->amount_cents / $order->total_cents);
            $proportionalReversal = (int) round($earned * $refundProportion);
            $toReverse = min($proportionalReversal, $remainingReversible);

            if ($toReverse <= 0) {
                return null;
            }

            $transaction = $this->ledger->post(
                customer: $order->customer,
                direction: PointTransaction::DIRECTION_ADJUST,
                points: -$toReverse,
                source: PointTransaction::SOURCE_REFUND_REVERSAL,
                sourceReferenceType: Refund::class,
                sourceReferenceId: $refund->id,
                note: ($refund->is_partial ? 'Partial' : 'Full')." refund on order #{$order->order_number} — {$toReverse} points reversed.",
                metadata: ['refund_amount_cents' => $refund->amount_cents, 'order_total_cents' => $order->total_cents],
                idempotencyKey: "refund_reversal:{$refund->shop_id}:{$refund->id}",
            );

            $refund->update(['points_reversed_at' => now(), 'point_transaction_id' => $transaction->id]);

            return $transaction;
        });
    }

    private function totalEarnedForOrder(Order $order): int
    {
        return (int) PointTransaction::query()
            ->where('shop_id', $order->shop_id)
            ->where('source_reference_type', Order::class)
            ->where('source_reference_id', $order->id)
            ->where('direction', PointTransaction::DIRECTION_EARN)
            ->sum('points');
    }

    /** Sums BOTH reversal types — a cancellation and a prior refund on the same order must never combine to reverse more than was earned. */
    private function totalReversedForOrder(Order $order): int
    {
        $cancellationReversals = (int) abs(PointTransaction::query()
            ->where('shop_id', $order->shop_id)
            ->where('source', PointTransaction::SOURCE_CANCELLATION_REVERSAL)
            ->where('source_reference_type', Order::class)
            ->where('source_reference_id', $order->id)
            ->sum('points'));

        $refundReversals = (int) abs(PointTransaction::query()
            ->where('shop_id', $order->shop_id)
            ->where('source', PointTransaction::SOURCE_REFUND_REVERSAL)
            ->whereIn('source_reference_id', $order->refunds()->pluck('id'))
            ->where('source_reference_type', Refund::class)
            ->sum('points'));

        return $cancellationReversals + $refundReversals;
    }
}
