<?php

namespace App\Services\Rewards;

use App\Exceptions\Rewards\InsufficientPointsException;
use App\Jobs\Rewards\FulfillRewardRedemptionJob;
use App\Models\Customer;
use App\Models\Point;
use App\Models\PointTransaction;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Repositories\Contracts\RewardRedemptionRepositoryInterface;
use App\Services\Points\PointsLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the full "spend points on a reward" workflow — see
 * docs/REWARDS_ENGINE.md#redemption-flow for the step-by-step
 * walkthrough and the locking-order rationale below.
 *
 * LOCKING ORDER (always Reward, then Point — never the reverse, in
 * every code path that touches both): `RewardEligibilityService`'s
 * usage-limit checks (total redemptions, per-customer redemptions,
 * stock) are only race-free because concurrent redemption attempts
 * for the SAME reward serialize on its row lock; the customer's
 * `Point` row lock is what protects the balance math specifically
 * (which could otherwise be affected by unrelated concurrent point-
 * earning activity for that customer). A consistent lock order across
 * every caller is what prevents a deadlock between two transactions
 * that both need both locks.
 *
 * Shopify discount creation is NEVER attempted inside this transaction
 * — holding a customer's `Point` row lock open across an external HTTP
 * call would block every other point operation for that customer for
 * as long as Shopify takes to respond, and the task's own "use queues
 * for long-running operations" requirement rules it out anyway. Instead:
 * this method's transaction ends with the redemption in `pending` and
 * points already deducted, then dispatches `FulfillRewardRedemptionJob`
 * to do the actual Shopify call in the background. If that job fails,
 * it — not this service — is responsible for the compensating refund;
 * see that job's docblock for why "roll back the transaction" is
 * implemented as an async compensating transaction rather than a literal
 * same-transaction rollback once a queued job has been dispatched.
 */
class RewardRedemptionService
{
    public function __construct(
        private readonly RewardRedemptionRepositoryInterface $redemptions,
        private readonly RewardEligibilityService $eligibility,
        private readonly PointsLedgerService $ledger,
    ) {}

    /**
     * @throws \App\Exceptions\Rewards\RewardNotRedeemableException
     * @throws \App\Exceptions\Rewards\CustomerNotEligibleException
     * @throws InsufficientPointsException
     */
    public function redeem(Customer $customer, Reward $reward, ?string $idempotencyKey = null): RewardRedemption
    {
        return DB::transaction(function () use ($customer, $reward, $idempotencyKey) {
            if ($idempotencyKey !== null) {
                $existing = RewardRedemption::query()
                    ->where('shop_id', $customer->shop_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            /** @var Reward $lockedReward */
            $lockedReward = Reward::query()->where('id', $reward->id)->lockForUpdate()->firstOrFail();

            $this->eligibility->assertRedeemable($lockedReward);
            $this->eligibility->assertWithinCustomerLimit($lockedReward, $customer);
            $this->eligibility->assertCustomerEligible($lockedReward, $customer);

            /** @var Point $lockedPoint */
            $lockedPoint = Point::query()->where('customer_id', $customer->id)->lockForUpdate()->firstOrFail();

            if ($lockedPoint->balance < $lockedReward->points_cost) {
                throw new InsufficientPointsException($lockedPoint->balance, $lockedReward->points_cost);
            }

            $redemption = $this->redemptions->create([
                'shop_id' => $customer->shop_id,
                'customer_id' => $customer->id,
                'reward_id' => $lockedReward->id,
                'points_spent' => $lockedReward->points_cost,
                'balance_before' => $lockedPoint->balance,
                'balance_after' => $lockedPoint->balance - $lockedReward->points_cost,
                'idempotency_key' => $idempotencyKey,
                'status' => RewardRedemption::STATUS_PENDING,
            ]);

            // Re-locks the SAME already-locked row — safe within one
            // transaction (Laravel/most drivers treat this as a no-op
            // re-acquisition on the same connection, not a second wait).
            $this->ledger->post(
                customer: $customer,
                direction: PointTransaction::DIRECTION_REDEEM,
                points: $lockedReward->points_cost,
                source: PointTransaction::SOURCE_REWARD_REDEMPTION,
                sourceReferenceType: RewardRedemption::class,
                sourceReferenceId: $redemption->id,
                note: "Redeemed for: {$lockedReward->name}",
                idempotencyKey: "reward_redemption:{$customer->shop_id}:{$redemption->id}",
            );

            FulfillRewardRedemptionJob::dispatch($redemption);

            return $redemption;
        });
    }
}
