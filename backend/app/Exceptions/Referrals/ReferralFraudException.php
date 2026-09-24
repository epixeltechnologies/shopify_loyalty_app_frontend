<?php

namespace App\Exceptions\Referrals;

use RuntimeException;

/** Thrown for an unambiguous fraud violation that blocks a referral outright (self-referral, an already-referred customer, a reused qualifying order) — as opposed to a SOFT flag, which pauses reward payout for review rather than rejecting immediately. See ReferralFraudDetectionService. */
class ReferralFraudException extends RuntimeException
{
    public function __construct(public readonly string $reasonCode, string $message)
    {
        parent::__construct($message);
    }
}
