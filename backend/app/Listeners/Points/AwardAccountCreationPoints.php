<?php

namespace App\Listeners\Points;

use App\Events\Customers\CustomerEnrolled;
use App\Services\Billing\SubscriptionAccessService;
use App\Services\Points\PointsAccrualService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Fires the one-time account-creation bonus off the existing
 * `CustomerEnrolled` event — enrollment
 * (`App\Services\Customers\CustomerService::enroll()`) stays completely
 * unaware of points, matching this app's established pattern of
 * side effects living in listeners, not the triggering service (see
 * `SendWelcomeNotification`, the other existing `CustomerEnrolled`
 * listener). `PointsAccrualService::accrueSignupBonus()` is itself
 * idempotent and a safe no-op if no `signup_bonus` rule is
 * configured/active — this listener adds only the subscription-gating
 * check (no active plan => no points processing, same rule every other
 * accrual path enforces).
 */
class AwardAccountCreationPoints implements ShouldQueue
{
    public function __construct(private readonly SubscriptionAccessService $access) {}

    public function handle(CustomerEnrolled $event): void
    {
        if (! $this->access->hasActiveSubscription($event->customer->shop)) {
            return;
        }

        app(PointsAccrualService::class)->accrueSignupBonus($event->customer);
    }
}
