<?php

namespace App\Jobs\VipTiers;

use App\Jobs\Concerns\HasDefaultRetryPolicy;
use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched daily per shop (see routes/console.php) — the "scheduled
 * evaluation" the task requires so a downgrade never happens off a
 * single webhook's timing. Deliberately does almost no work itself:
 * chunks through the shop's customers and fans each chunk out to its
 * OWN queued `EvaluateVipTierBatchJob`, so a shop with tens of
 * thousands of customers never blocks one worker/request evaluating
 * them all synchronously — this job's own runtime stays proportional
 * to "how many chunks," not "how many customers."
 */
class EvaluateShopVipTiersJob implements ShouldQueue
{
    use Dispatchable, HasDefaultRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 250;

    public function __construct(public readonly Shop $shop) {}

    public function handle(): void
    {
        Customer::query()
            ->where('shop_id', $this->shop->id)
            ->where('status', 'active')
            ->select('id')
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($customers) {
                EvaluateVipTierBatchJob::dispatch($this->shop, $customers->pluck('id')->all());
            });
    }
}
