<?php

namespace App\Jobs\Points;

use App\Jobs\Concerns\HasDefaultRetryPolicy;
use App\Models\Customer;
use App\Models\Shop;
use App\Services\Billing\SubscriptionAccessService;
use App\Services\Notifications\NotificationService;
use App\Services\Points\PointsAccrualService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched daily per shop (see routes/console.php) — finds every
 * enrolled, active customer whose `birthday_month`/`birthday_day`
 * matches today (see `customers` migration's privacy note: only
 * month/day are ever stored, never a birth year) and awards the
 * configured `birthday_bonus` rule's points via
 * `PointsAccrualService::accrueBirthdayBonus()`, which is itself
 * idempotent per calendar year — running this job twice in one day (or
 * this shop having no birthday rule configured at all) is always a safe
 * no-op, never a duplicate award.
 */
class AwardBirthdayPointsJob implements ShouldQueue
{
    use Dispatchable, HasDefaultRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Shop $shop) {}

    public function handle(PointsAccrualService $accrual, SubscriptionAccessService $access, NotificationService $notifications): void
    {
        if (! $access->hasActiveSubscription($this->shop)) {
            return;
        }

        $today = now();
        $year = $today->year;

        $customers = Customer::query()
            ->where('shop_id', $this->shop->id)
            ->where('status', 'active')
            ->where('birthday_month', $today->month)
            ->where('birthday_day', $today->day)
            ->get();

        $awarded = 0;

        foreach ($customers as $customer) {
            $transaction = $accrual->accrueBirthdayBonus($customer, $year);

            if ($transaction) {
                $awarded++;
                $notifications->notify($this->shop, $customer, 'birthday_reward', ['points' => (string) $transaction->points]);
            }
        }

        if ($awarded > 0) {
            Log::info('Birthday points awarded', ['shop' => $this->shop->shopify_domain, 'count' => $awarded]);
        }
    }
}
