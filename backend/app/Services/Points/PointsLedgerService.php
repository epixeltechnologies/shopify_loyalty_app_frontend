<?php

namespace App\Services\Points;

use App\Events\Points\PointsPosted;
use App\Models\Customer;
use App\Models\Point;
use App\Models\PointTransaction;
use App\Repositories\Contracts\PointTransactionRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * POINT SYSTEM: the ONLY code path allowed to write a points balance
 * change. Every earn/redeem/expire/adjust is posted as an immutable
 * `point_transactions` ledger row inside a transaction with
 * `SELECT ... FOR UPDATE` on the customer's `points` row, so concurrent
 * requests (e.g. two webhooks for the same customer) can never race past
 * each other and produce an incorrect balance.
 *
 * Business rules for *how many* points a given action earns (point-rule
 * evaluation, VIP multipliers, etc.) live in PointsAccrualService/
 * PointCalculationService — this class only knows how to post a given,
 * already-decided amount safely. See docs/POINTS_ENGINE.md.
 */
class PointsLedgerService
{
    public function __construct(private readonly PointTransactionRepositoryInterface $transactions) {}

    /**
     * Posts a ledger entry. When `$idempotencyKey` is provided, this is
     * safe to call more than once for "the same event" (a retried job,
     * a redelivered webhook, two near-simultaneous requests for the
     * same order/customer) — a duplicate call returns the ORIGINAL
     * transaction unchanged rather than posting twice. Two layers make
     * this safe under concurrency, not just under sequential retries:
     *
     *   1. The idempotency check happens INSIDE the same locked
     *      transaction as the balance read/write (after acquiring
     *      `lockForUpdate()` on the customer's `points` row) — a second
     *      concurrent caller for the same customer blocks on the lock
     *      until the first commits, then sees the now-existing row.
     *   2. `point_transactions` has a UNIQUE `(shop_id, idempotency_key)`
     *      index as a hard backstop — if step 1's check is ever raced
     *      by something outside this customer's lock (which shouldn't
     *      happen given every idempotency key used in this app is
     *      scoped to one customer's event, but is not architecturally
     *      guaranteed forever), the resulting `QueryException` is
     *      caught and the existing row is fetched and returned instead
     *      of letting a duplicate-key error propagate as a failure.
     */
    public function post(
        Customer $customer,
        string $direction,
        int $points,
        string $source,
        ?string $sourceReferenceType = null,
        ?int $sourceReferenceId = null,
        ?string $note = null,
        ?array $metadata = null,
        ?\DateTimeInterface $expiresAt = null,
        ?int $pointRuleId = null,
        ?string $idempotencyKey = null,
    ): PointTransaction {
        return DB::transaction(function () use ($customer, $direction, $points, $source, $sourceReferenceType, $sourceReferenceId, $note, $metadata, $expiresAt, $pointRuleId, $idempotencyKey) {
            /** @var Point $locked */
            $locked = Point::query()->where('customer_id', $customer->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null) {
                $existing = PointTransaction::query()
                    ->where('shop_id', $locked->shop_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $signedPoints = in_array($direction, [PointTransaction::DIRECTION_REDEEM, PointTransaction::DIRECTION_EXPIRE], true)
                ? -abs($points)
                : ($direction === PointTransaction::DIRECTION_ADJUST ? $points : abs($points));

            $newBalance = $locked->balance + $signedPoints;

            try {
                $transaction = $this->transactions->create([
                    'shop_id' => $locked->shop_id,
                    'customer_id' => $locked->customer_id,
                    'point_rule_id' => $pointRuleId,
                    'direction' => $direction,
                    'points' => $signedPoints,
                    'balance_after' => $newBalance,
                    'source' => $source,
                    'source_reference_type' => $sourceReferenceType,
                    'source_reference_id' => $sourceReferenceId,
                    'note' => $note,
                    'metadata' => $metadata,
                    'idempotency_key' => $idempotencyKey,
                    'expires_at' => $expiresAt,
                ]);
            } catch (QueryException $e) {
                if ($idempotencyKey !== null && $this->isDuplicateKeyViolation($e)) {
                    return PointTransaction::query()
                        ->where('shop_id', $locked->shop_id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->firstOrFail();
                }

                throw $e;
            }

            $locked->update([
                'balance' => $newBalance,
                'lifetime_earned' => $direction === PointTransaction::DIRECTION_EARN ? $locked->lifetime_earned + $signedPoints : $locked->lifetime_earned,
                'lifetime_redeemed' => $direction === PointTransaction::DIRECTION_REDEEM ? $locked->lifetime_redeemed + abs($signedPoints) : $locked->lifetime_redeemed,
                'lifetime_expired' => $direction === PointTransaction::DIRECTION_EXPIRE ? $locked->lifetime_expired + abs($signedPoints) : $locked->lifetime_expired,
                'lifetime_adjusted' => $direction === PointTransaction::DIRECTION_ADJUST ? $locked->lifetime_adjusted + $signedPoints : $locked->lifetime_adjusted,
                'last_transaction_at' => now(),
            ]);

            event(new PointsPosted($transaction));

            return $transaction;
        });
    }

    private function isDuplicateKeyViolation(QueryException $e): bool
    {
        // SQLSTATE 23000 = integrity constraint violation, covering
        // both MySQL's and SQLite's unique-index error codes — driver-
        // agnostic rather than string-matching a driver-specific message.
        return $e->getCode() === '23000';
    }
}
