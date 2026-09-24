<?php

namespace App\Jobs\VipTiers;

use App\Jobs\Concerns\HasDefaultRetryPolicy;
use App\Models\Customer;
use App\Models\Shop;
use App\Services\VipTiers\VipEvaluationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Evaluates a bounded batch of customers (dispatched by
 * `EvaluateShopVipTiersJob`) — the actual per-customer work unit,
 * small enough that one failing/slow customer's evaluation doesn't
 * hold up a shop's entire daily run, and small enough that a queue
 * worker's memory/time budget is never at risk regardless of the
 * shop's total customer count.
 */
class EvaluateVipTierBatchJob implements ShouldQueue
{
    use Dispatchable, HasDefaultRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    /** @param  int[]  $customerIds */
    public function __construct(public readonly Shop $shop, public readonly array $customerIds) {}

    public function handle(VipEvaluationService $evaluation): void
    {
        $customers = Customer::query()
            ->where('shop_id', $this->shop->id)
            ->whereIn('id', $this->customerIds)
            ->get();

        foreach ($customers as $customer) {
            try {
                $evaluation->evaluateFully($customer);
            } catch (\Throwable $e) {
                // One customer's evaluation failing (an unexpected data
                // state, a transient DB error) must never abort the rest
                // of the batch.
                Log::warning('VIP tier evaluation failed for customer', ['customer_id' => $customer->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
