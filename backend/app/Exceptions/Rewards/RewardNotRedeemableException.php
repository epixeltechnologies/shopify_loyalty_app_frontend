<?php

namespace App\Exceptions\Rewards;

use RuntimeException;

/** Thrown for any reason a specific reward can't be redeemed right now — inactive, outside its date window, out of stock, or a usage limit already reached. */
class RewardNotRedeemableException extends RuntimeException
{
    public function __construct(public readonly string $reasonCode, string $message)
    {
        parent::__construct($message);
    }
}
