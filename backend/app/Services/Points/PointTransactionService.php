<?php

namespace App\Services\Points;

use App\Models\Customer;
use App\Models\Shop;
use App\Repositories\Contracts\PointTransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * POINT SYSTEM: read-side access to the ledger — a customer's paginated
 * history, and shop-wide aggregate statistics for the merchant admin
 * dashboard. Never writes; PointsLedgerService is the only writer.
 */
class PointTransactionService
{
    public function __construct(private readonly PointTransactionRepositoryInterface $transactions) {}

    public function historyFor(Customer $customer, int $perPage = 25): LengthAwarePaginator
    {
        return $this->transactions->paginateForCustomer($customer, $perPage);
    }

    /**
     * Shop-wide point statistics — total issued/redeemed/expired/adjusted
     * across all customers, and how many customers have ever earned a
     * point at all. Computed directly from the ledger (not the `points`
     * cache table) specifically because this is a reporting/admin
     * endpoint, not a hot per-request path — correctness from the
     * source of truth matters more here than avoiding one aggregate query.
     */
    public function statisticsFor(Shop $shop): array
    {
        $totals = DB::table('point_transactions')
            ->where('shop_id', $shop->id)
            ->selectRaw("
                SUM(CASE WHEN direction = 'earn' THEN points ELSE 0 END) as total_earned,
                SUM(CASE WHEN direction = 'redeem' THEN -points ELSE 0 END) as total_redeemed,
                SUM(CASE WHEN direction = 'expire' THEN -points ELSE 0 END) as total_expired,
                SUM(CASE WHEN direction = 'adjust' THEN points ELSE 0 END) as total_adjusted,
                COUNT(DISTINCT customer_id) as customers_with_activity,
                COUNT(*) as transaction_count
            ")
            ->first();

        return [
            'total_points_earned' => (int) ($totals->total_earned ?? 0),
            'total_points_redeemed' => (int) ($totals->total_redeemed ?? 0),
            'total_points_expired' => (int) ($totals->total_expired ?? 0),
            'total_points_adjusted' => (int) ($totals->total_adjusted ?? 0),
            'customers_with_activity' => (int) ($totals->customers_with_activity ?? 0),
            'transaction_count' => (int) ($totals->transaction_count ?? 0),
        ];
    }
}
