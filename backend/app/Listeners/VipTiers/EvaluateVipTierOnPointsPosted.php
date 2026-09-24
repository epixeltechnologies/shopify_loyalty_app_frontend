<?php

namespace App\Listeners\VipTiers;

use App\Events\Points\PointsPosted;
use App\Services\VipTiers\VipEvaluationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Re-evaluates a customer's VIP tier after every posted points
 * transaction, decoupling PointsLedgerService (which only knows how to
 * post a ledger entry) from VIP tier logic entirely. Deliberately calls
 * `evaluateForUpgradeOnly()`, never `evaluateFully()` — a single points
 * event (including a redemption or expiry that LOWERS a rolling-period
 * metric) must never immediately cost a customer their tier; see
 * VipEvaluationService's docblock and docs/VIP_TIERS.md.
 */
class EvaluateVipTierOnPointsPosted implements ShouldQueue
{
    public function __construct(private readonly VipEvaluationService $evaluation) {}

    public function handle(PointsPosted $event): void
    {
        $this->evaluation->evaluateForUpgradeOnly($event->transaction->customer);
    }
}
