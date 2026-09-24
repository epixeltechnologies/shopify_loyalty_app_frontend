<?php

namespace App\Exceptions\Rewards;

use RuntimeException;

/** Thrown when a customer doesn't meet a reward's `customer_eligibility` rule (e.g. a required minimum VIP tier). */
class CustomerNotEligibleException extends RuntimeException
{
    public function __construct(string $message = 'This customer is not eligible for this reward.')
    {
        parent::__construct($message);
    }
}
