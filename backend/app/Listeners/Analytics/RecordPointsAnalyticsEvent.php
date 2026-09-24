<?php

namespace App\Listeners\Analytics;

use App\Events\Points\PointsPosted;
use App\Models\PointTransaction;
use App\Services\Analytics\EventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * ANALYTICS: translates every ledger post into a `points.earned` /
 * `points.redeemed` / `points.expired` / `points.adjusted` analytics
 * event — decoupled from PointsLedgerService itself (which stays
 * focused purely on ledger correctness) via the *existing* `PointsPosted`
 * event, the same pattern `EvaluateVipTierOnPointsPosted` already uses.
 * Only non-PII fields (amount, source, direction) go into `properties`
 * — never the customer's name/email, per the task's explicit
 * "do not store unnecessary sensitive personal information" requirement.
 */
class RecordPointsAnalyticsEvent implements ShouldQueue
{
    private const EVENT_TYPES = [
        PointTransaction::DIRECTION_EARN => 'points.earned',
        PointTransaction::DIRECTION_REDEEM => 'points.redeemed',
        PointTransaction::DIRECTION_EXPIRE => 'points.expired',
        PointTransaction::DIRECTION_ADJUST => 'points.adjusted',
    ];

    public function __construct(private readonly EventRecorder $events) {}

    public function handle(PointsPosted $event): void
    {
        $transaction = $event->transaction;
        $eventType = self::EVENT_TYPES[$transaction->direction] ?? null;

        if (! $eventType) {
            return;
        }

        $this->events->record($transaction->customer->shop, $eventType, $transaction->customer, [
            'points' => $transaction->points,
            'source' => $transaction->source,
        ]);
    }
}
