<?php

namespace App\Services\Points;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

/**
 * POINT SYSTEM: the actual expiration logic `App\Jobs\Points\ExpirePointsJob`
 * (dispatched daily, per shop — see routes/console.php) delegates to.
 * Expiration is opt-in per shop (`shop_settings.points_expiry_days`,
 * null = points never expire) — see docs/POINTS_ENGINE.md.
 *
 * Never deletes anything: an `earn` transaction whose `expires_at` has
 * passed gets a matching `expire` ledger entry posted against it,
 * idempotently keyed so the same earn-transaction's expiry is only ever
 * processed once even if this job runs twice for the same day (a
 * retried job, an overlapping schedule).
 */
class PointExpirationService
{
    /**
     * Processes every customer with at least one newly-expired,
     * not-yet-expired `earn` transaction for this shop. Runs per-customer
     * so one customer's failure (an unexpected data state) doesn't abort
     * the rest of the shop's expiry run — each iteration is its own
     * atomic unit via PointsLedgerService's own transaction.
     */
    public function expireForShop(Shop $shop): int
    {
        $customerIds = PointTransaction::query()
            ->where('shop_id', $shop->id)
            ->where('direction', PointTransaction::DIRECTION_EARN)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->distinct()
            ->pluck('customer_id');

        $processed = 0;

        foreach ($customerIds as $customerId) {
            $customer = Customer::query()->find($customerId);
            if (! $customer) {
                continue;
            }

            $processed += $this->expireForCustomer($shop, $customer);
        }

        return $processed;
    }

    /** @return int number of expire transactions posted for this customer */
    private function expireForCustomer(Shop $shop, Customer $customer): int
    {
        $expiredEarnRows = PointTransaction::query()
            ->where('shop_id', $shop->id)
            ->where('customer_id', $customer->id)
            ->where('direction', PointTransaction::DIRECTION_EARN)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $posted = 0;

        foreach ($expiredEarnRows as $earnRow) {
            // One expire transaction per originating earn row, keyed to
            // IT specifically — the idempotency key is what guarantees
            // "never expire the same earned points twice" regardless of
            // how many times this job runs.
            $idempotencyKey = "points_expiry:{$shop->id}:{$earnRow->id}";

            $alreadyExpired = PointTransaction::query()
                ->where('shop_id', $shop->id)
                ->where('idempotency_key', $idempotencyKey)
                ->exists();

            if ($alreadyExpired) {
                continue;
            }

            app(PointsLedgerService::class)->post(
                customer: $customer,
                direction: PointTransaction::DIRECTION_EXPIRE,
                points: $earnRow->points,
                source: PointTransaction::SOURCE_EXPIRATION,
                sourceReferenceType: PointTransaction::class,
                sourceReferenceId: $earnRow->id,
                note: "{$earnRow->points} points earned on ".$earnRow->created_at->toDateString().' expired.',
                idempotencyKey: $idempotencyKey,
            );

            $posted++;
        }

        if ($posted > 0) {
            Log::info('Points expired for customer', ['shop' => $shop->shopify_domain, 'customer_id' => $customer->id, 'transactions_posted' => $posted]);
        }

        return $posted;
    }
}
