<?php

namespace App\Jobs\Points;

use App\Jobs\Concerns\HasDefaultRetryPolicy;
use App\Models\Shop;
use App\Services\Points\PointExpirationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched daily per shop (see routes/console.php) to post `expire`
 * ledger entries for any earned points whose `expires_at` has passed.
 * Expiry policy (whether points expire at all, and after how many days)
 * is a per-shop setting — `shop_settings.points_expiry_days` — read at
 * the point `PointsAccrualService` sets `expires_at` on an `earn`
 * transaction, not here; this job only reacts to whatever `expires_at`
 * values already exist in the ledger.
 */
class ExpirePointsJob implements ShouldQueue
{
    use Dispatchable, HasDefaultRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Shop $shop) {}

    public function handle(PointExpirationService $expiration): void
    {
        $count = $expiration->expireForShop($this->shop);

        if ($count > 0) {
            Log::info('Daily points expiry completed', ['shop' => $this->shop->shopify_domain, 'transactions_posted' => $count]);
        }
    }
}
