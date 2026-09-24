<?php

namespace App\Exceptions\Rewards;

use RuntimeException;

class InsufficientPointsException extends RuntimeException
{
    public function __construct(public readonly int $currentBalance, public readonly int $required)
    {
        parent::__construct("This reward requires {$required} points; the customer only has {$currentBalance}.");
    }
}
