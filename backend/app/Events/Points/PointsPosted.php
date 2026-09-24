<?php

namespace App\Events\Points;

use App\Models\PointTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired for every ledger entry (earn/redeem/expire/adjust) by
 * PointsLedgerService. Listeners can react generically (e.g. VIP tier
 * re-evaluation) without each caller of the ledger needing to know
 * about downstream effects.
 */
class PointsPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly PointTransaction $transaction) {}
}
