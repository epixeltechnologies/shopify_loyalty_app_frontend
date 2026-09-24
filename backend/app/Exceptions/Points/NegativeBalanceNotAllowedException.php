<?php

namespace App\Exceptions\Points;

use RuntimeException;

/** Thrown by PointAdjustmentService when a removal would take the balance negative and the shop hasn't opted into allowing that. */
class NegativeBalanceNotAllowedException extends RuntimeException
{
    public function __construct(public readonly int $currentBalance, public readonly int $requestedChange)
    {
        parent::__construct("This adjustment would result in a negative balance ({$currentBalance} {$requestedChange}), which this shop does not allow.");
    }
}
