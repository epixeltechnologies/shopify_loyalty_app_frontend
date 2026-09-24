<?php

namespace App\Exceptions\Billing;

use App\DTOs\DowngradeEligibility;
use RuntimeException;

/**
 * Thrown by BillingService when a requested plan change would leave the
 * shop over the target plan's limits — see PlanService::checkDowngradeEligibility().
 * Carries the full DowngradeEligibility result so the controller can
 * render the specific blockers rather than a generic error.
 */
class DowngradeBlockedException extends RuntimeException
{
    public function __construct(public readonly DowngradeEligibility $eligibility)
    {
        parent::__construct('This plan change is blocked because current usage exceeds the target plan\'s limits.');
    }
}
