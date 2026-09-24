<?php

namespace App\Services\Points;

use App\Exceptions\Points\ManualAdjustmentsDisabledException;
use App\Exceptions\Points\NegativeBalanceNotAllowedException;
use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Shop;
use App\Services\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;

/**
 * POINT SYSTEM: the only path for a merchant admin to manually add or
 * remove points. Requires a reason (enforced at the FormRequest layer —
 * see AdjustPointsRequest — not re-validated here, since an empty
 * reason should never reach a service method in the first place) and
 * always writes an audit trail entry (App\Services\Audit\AuditLogger)
 * in addition to the point_transactions row itself — two independent
 * records of "who changed this and why," matching this app's general
 * audit-trail convention for security/business-sensitive mutations.
 *
 * Never allows a removal to take the balance negative unless the shop
 * has explicitly opted in (`shop_settings.allow_negative_point_balance`)
 * — see docs/POINTS_ENGINE.md#manual-adjustments.
 */
class PointAdjustmentService
{
    public function __construct(
        private readonly PointsLedgerService $ledger,
        private readonly PointAccountService $accounts,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  int  $points  Signed — positive to add, negative to remove.
     * @param  string  $adminIdentity  Free-text identifier for who performed this (see docs/POINTS_ENGINE.md's note on staff-account auth status).
     *
     * @throws NegativeBalanceNotAllowedException
     */
    public function adjust(Shop $shop, Customer $customer, int $points, string $reason, string $adminIdentity): PointTransaction
    {
        if ($shop->setting && ! $shop->setting->allow_manual_point_adjustments) {
            throw new ManualAdjustmentsDisabledException;
        }

        $this->assertBalanceAllowed($shop, $customer, $points);

        $transaction = $this->ledger->post(
            customer: $customer,
            direction: PointTransaction::DIRECTION_ADJUST,
            points: $points,
            source: PointTransaction::SOURCE_MANUAL_ADJUSTMENT,
            note: $reason,
            metadata: ['admin_identity' => $adminIdentity],
        );

        $this->audit->log(
            shop: $shop,
            action: 'points.manual_adjustment',
            auditableType: Customer::class,
            auditableId: $customer->id,
            changes: ['points' => $points, 'reason' => $reason, 'admin_identity' => $adminIdentity, 'balance_after' => $transaction->balance_after],
        );

        return $transaction;
    }

    /**
     * A distinct entry point from `adjust()` for a SYSTEM-initiated
     * correction (e.g. reconciling a data-migration discrepancy) rather
     * than a merchant admin action — same negative-balance guard, no
     * admin identity, a different audit action name and `source` so the
     * two are never conflated in reporting.
     */
    public function correct(Shop $shop, Customer $customer, int $points, string $reason): PointTransaction
    {
        $this->assertBalanceAllowed($shop, $customer, $points);

        $transaction = $this->ledger->post(
            customer: $customer,
            direction: PointTransaction::DIRECTION_ADJUST,
            points: $points,
            source: PointTransaction::SOURCE_ADMINISTRATIVE_CORRECTION,
            note: $reason,
        );

        $this->audit->log(
            shop: $shop,
            action: 'points.administrative_correction',
            actorType: 'system',
            auditableType: Customer::class,
            auditableId: $customer->id,
            changes: ['points' => $points, 'reason' => $reason, 'balance_after' => $transaction->balance_after],
        );

        return $transaction;
    }

    private function assertBalanceAllowed(Shop $shop, Customer $customer, int $points): void
    {
        if ($points >= 0) {
            return; // adding points never risks a negative balance
        }

        $allowNegative = $shop->setting?->allow_negative_point_balance ?? false;
        if ($allowNegative) {
            return;
        }

        $currentBalance = $this->accounts->balance($customer);
        if ($currentBalance + $points < 0) {
            throw new NegativeBalanceNotAllowedException($currentBalance, $points);
        }
    }
}
