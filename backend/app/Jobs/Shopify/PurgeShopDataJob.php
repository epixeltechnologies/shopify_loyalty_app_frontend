<?php

namespace App\Jobs\Shopify;

use App\Jobs\Concerns\HasDefaultRetryPolicy;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Performs the actual data deletion for the `shop/redact` GDPR webhook
 * (dispatched by HandleShopRedactJob). Split into its own job — rather
 * than inlined in the webhook handler — specifically so it runs on the
 * `webhooks` queue independently of the webhook's own retry budget: a
 * large shop's purge can take longer than a single webhook-processing
 * attempt should, and a transient failure mid-purge shouldn't re-run
 * the entire webhook-receipt bookkeeping, only the remaining deletion.
 *
 * Deletion order: `customers` first, in chunks — every customer-owned
 * table (`points`, `point_transactions`, `reward_redemptions`,
 * `customer_vip_history`, `referral_rewards`, `referrals` as referrer)
 * cascades at the database level from `customers.id`, so chunking the
 * customer deletion IS the large-data-cleanup chunking this task asks
 * for, without needing to hand-chunk every child table separately.
 * Shop-level (non-customer-owned) tables are removed after, then the
 * `Shop` row itself.
 *
 * Deliberately NOT deleted: `webhooks` and `audit_logs` — both already
 * have `nullOnDelete` on `shop_id` precisely so the compliance/audit
 * trail of "this shop was redacted, and when" survives the shop's own
 * deletion, which is itself often a legally-relevant record to retain.
 */
class PurgeShopDataJob implements ShouldQueue
{
    use Dispatchable, HasDefaultRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    private const CUSTOMER_CHUNK_SIZE = 500;

    public function __construct(public readonly int $shopId) {}

    public function handle(): void
    {
        $shop = Shop::query()->find($this->shopId);

        if (! $shop) {
            Log::info('Shop redaction purge skipped — shop already removed', ['shop_id' => $this->shopId]);

            return;
        }

        $domain = $shop->shopify_domain; // captured before the row is gone, for the final log line

        $this->purgeCustomers($shop);
        $this->purgeShopScopedTables($shop);

        $shop->forceDelete(); // hard delete — a soft-deleted row would still carry PII (name/email/domain)

        Log::info('Shop data purge completed', ['shop_domain' => $domain]);
    }

    private function purgeCustomers(Shop $shop): void
    {
        // Raw query builder (not Eloquent) throughout this job — this is
        // a hard, unconditional purge that must bypass soft-delete
        // scoping and model events entirely, not a "soft delete of
        // already-soft-deleted rows." DB::table() deletes are always
        // real row deletions regardless of whether the model uses
        // SoftDeletes.
        DB::table('customers')
            ->where('shop_id', $shop->id)
            ->orderBy('id')
            ->chunkById(self::CUSTOMER_CHUNK_SIZE, function ($customers) {
                DB::table('customers')->whereIn('id', $customers->pluck('id'))->delete();
            });
    }

    private function purgeShopScopedTables(Shop $shop): void
    {
        foreach ([
            'vip_tiers', 'rewards', 'point_rules', 'shop_settings',
            'subscriptions', 'subscription_usage',
            'analytics_events', 'analytics_daily_snapshots',
        ] as $table) {
            DB::table($table)->where('shop_id', $shop->id)->delete();
        }
    }
}
