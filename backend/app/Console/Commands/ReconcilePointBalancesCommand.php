<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Point;
use App\Models\PointTransaction;
use App\Models\Shop;
use App\Services\Points\PointAdjustmentService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OPS: `php artisan points:reconcile` — see docs/BACKUP_AND_RECOVERY.md's
 * "Point data recovery" section, which this command formalizes into a
 * runnable tool. The append-only `point_transactions` ledger is this
 * app's source of truth (docs/POINTS_ENGINE.md, ADR-002); `points.balance`
 * is a maintained CACHE of that ledger's sum. This command finds any
 * customer where the two disagree.
 *
 * DELIBERATELY NEVER silently modifies a balance. Reporting is always
 * safe to run repeatedly, any time, in production, with zero side
 * effects. Repair is a distinct, explicit, confirmed action — and even
 * then, never edits historical `point_transactions` rows (which stay
 * immutable per this app's core design); it posts a NEW, audited,
 * ledger-backed compensating transaction via the existing
 * `PointAdjustmentService::correct()`, exactly as
 * docs/BACKUP_AND_RECOVERY.md documents doing by hand.
 */
class ReconcilePointBalancesCommand extends Command
{
    protected $signature = 'points:reconcile
        {--shop= : Only reconcile a specific shop (by shopify_domain)}
        {--repair : Post a compensating correction for every discrepancy found (requires confirmation, or --force)}
        {--force : Skip the confirmation prompt when using --repair — use only in a controlled, reviewed maintenance window}';

    protected $description = 'Report (and optionally repair) discrepancies between the point ledger and cached balances, without ever editing historical transactions.';

    public function handle(PointAdjustmentService $adjustments): int
    {
        $shopsQuery = Shop::query()->where('is_installed', true);

        if ($shopDomain = $this->option('shop')) {
            $shopsQuery->where('shopify_domain', $shopDomain);
        }

        $shops = $shopsQuery->get();

        if ($shops->isEmpty()) {
            $this->warn('No matching installed shop(s) found.');

            return self::SUCCESS;
        }

        $totalDiscrepancies = 0;
        $totalCustomersChecked = 0;

        foreach ($shops as $shop) {
            // Deliberately scope every query to this shop explicitly
            // (this is an ops tool iterating ACROSS shops, so it can't
            // rely on the ambient TenantContext the way a normal
            // request-scoped controller/service does) — see
            // docs/MULTI_TENANCY.md for why every cross-tenant tool in
            // this app takes this same explicit-scoping approach.
            TenantContext::set($shop);

            $ledgerSums = PointTransaction::query()
                ->where('shop_id', $shop->id)
                ->select('customer_id', DB::raw('SUM(points) as ledger_sum'))
                ->groupBy('customer_id')
                ->pluck('ledger_sum', 'customer_id');

            $cachedBalances = Point::query()
                ->where('shop_id', $shop->id)
                ->pluck('balance', 'customer_id');

            $customerIds = $ledgerSums->keys()->merge($cachedBalances->keys())->unique();
            $totalCustomersChecked += $customerIds->count();

            $discrepancies = [];

            foreach ($customerIds as $customerId) {
                $ledgerSum = (int) ($ledgerSums[$customerId] ?? 0);
                $cachedBalance = (int) ($cachedBalances[$customerId] ?? 0);

                if ($ledgerSum !== $cachedBalance) {
                    $discrepancies[] = [
                        'customer_id' => $customerId,
                        'ledger_sum' => $ledgerSum,
                        'cached_balance' => $cachedBalance,
                        'difference' => $cachedBalance - $ledgerSum,
                    ];
                }
            }

            TenantContext::clear();

            if (empty($discrepancies)) {
                $this->line("[{$shop->shopify_domain}] OK — no discrepancies across {$customerIds->count()} customer(s).");

                continue;
            }

            $totalDiscrepancies += count($discrepancies);
            $this->error("[{$shop->shopify_domain}] ".count($discrepancies).' discrepancy(ies) found:');
            $this->table(
                ['Customer ID', 'Ledger sum', 'Cached balance', 'Difference'],
                collect($discrepancies)->map(fn ($d) => [$d['customer_id'], $d['ledger_sum'], $d['cached_balance'], $d['difference']])
            );

            Log::channel('security')->warning('point_balance_discrepancy_detected', [
                'shop_id' => $shop->id,
                'shop_domain' => $shop->shopify_domain,
                'discrepancy_count' => count($discrepancies),
            ]);

            if ($this->option('repair')) {
                $this->repair($shop, $discrepancies, $adjustments);
            }
        }

        $this->newLine();
        $this->info("Checked {$totalCustomersChecked} customer(s) across {$shops->count()} shop(s). Total discrepancies: {$totalDiscrepancies}.");

        return $totalDiscrepancies > 0 && ! $this->option('repair') ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<int, array{customer_id: int, ledger_sum: int, cached_balance: int, difference: int}> $discrepancies */
    private function repair(Shop $shop, array $discrepancies, PointAdjustmentService $adjustments): void
    {
        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                "Post ".count($discrepancies)." compensating correction(s) for [{$shop->shopify_domain}]? ".
                'This posts new, audited ledger entries — it never edits history — but is still a production data change.',
                default: false,
            );

            if (! $confirmed) {
                $this->warn('Repair skipped for this shop (not confirmed).');

                return;
            }
        }

        TenantContext::set($shop);

        foreach ($discrepancies as $discrepancy) {
            $customer = Customer::query()->find($discrepancy['customer_id']);

            if (! $customer) {
                $this->warn("Customer {$discrepancy['customer_id']} no longer exists — skipping.");

                continue;
            }

            // Bring the cached balance back in line with the ledger sum
            // (the ledger is the source of truth) — post whatever signed
            // amount closes the gap.
            $correctionAmount = $discrepancy['ledger_sum'] - $discrepancy['cached_balance'];

            $adjustments->correct(
                shop: $shop,
                customer: $customer,
                points: $correctionAmount,
                reason: 'Automated reconciliation: cached balance did not match ledger sum (points:reconcile --repair).',
            );

            $this->line("  Corrected customer {$customer->id} by {$correctionAmount} points.");
        }

        TenantContext::clear();
    }
}
