<?php

namespace App\Services\Points;

use App\Models\Customer;
use App\Models\Point;

/**
 * POINT SYSTEM: owns the `Point` account row itself — retrieval and
 * the guarantee that every enrolled customer has exactly one. Account
 * creation happens at enrollment time
 * (`App\Services\Customers\CustomerService::enroll()`, in the same
 * transaction as the `Customer` row) — `getOrCreate()` here exists as a
 * defensive fallback for any customer row that predates that guarantee
 * or was created through a path that skipped it, not the primary
 * creation path.
 *
 * "One active points account per customer per tenant" is enforced at
 * the database level by `points`'s `UNIQUE (shop_id, customer_id)`
 * constraint (see that migration), not just application logic — a
 * second concurrent `getOrCreate()` call for the same customer relies
 * on that constraint (via `firstOrCreate`, which itself is not
 * perfectly race-free) as the backstop, the same defense-in-depth
 * pattern used throughout this app's concurrency-sensitive paths.
 */
class PointAccountService
{
    public function getOrCreate(Customer $customer): Point
    {
        return Point::query()->firstOrCreate(
            ['shop_id' => $customer->shop_id, 'customer_id' => $customer->id],
        );
    }

    public function balance(Customer $customer): int
    {
        return $this->getOrCreate($customer)->balance;
    }

    public function summary(Customer $customer): array
    {
        $account = $this->getOrCreate($customer);

        return [
            'balance' => $account->balance,
            'lifetime_earned' => $account->lifetime_earned,
            'lifetime_redeemed' => $account->lifetime_redeemed,
            'lifetime_expired' => $account->lifetime_expired,
            'lifetime_adjusted' => $account->lifetime_adjusted,
            'pending_expiry_amount' => $account->pending_expiry_amount,
            'next_expiry_date' => $account->next_expiry_date?->toDateString(),
            'last_transaction_at' => $account->last_transaction_at?->toIso8601String(),
        ];
    }
}
