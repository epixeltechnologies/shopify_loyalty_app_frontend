<?php

namespace App\Services\Points;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\Referral;
use App\Repositories\Contracts\PointRuleRepositoryInterface;
use App\Services\VipTiers\VipBenefitService;
use Illuminate\Support\Collection;

/**
 * POINT SYSTEM: evaluates a shop's active PointRules against a
 * triggering event (order paid, signup, referral completed, birthday,
 * review) and decides how many points to award, then posts them via
 * PointsLedgerService. This is the orchestration layer — the actual
 * "how many points" math lives in PointCalculationService (purchase
 * rules); this class's job is picking which rule(s) apply and turning
 * the result into a ledger post with the correct idempotency key.
 *
 * Every accrual method is safe to call more than once for the same
 * event (a retried job, a redelivered webhook, a duplicate scheduled
 * run) — each constructs a deterministic idempotency key and relies on
 * PointsLedgerService's idempotency guarantee, never its own
 * check-then-post logic.
 */
class PointsAccrualService
{
    public function __construct(
        private readonly PointRuleRepositoryInterface $rules,
        private readonly PointCalculationService $calculator,
        private readonly PointsLedgerService $ledger,
        private readonly VipBenefitService $vipBenefits,
    ) {}

    /**
     * Awards purchase points for a paid order — see
     * `App\Jobs\Webhooks\HandleOrderCreatedJob`/`HandleOrderUpdatedJob`,
     * called once the synced `Order.financial_status` is `'paid'`.
     * Evaluates EVERY active `points_per_dollar` rule (a shop may run
     * more than one simultaneously, e.g. a base rate plus a promotional
     * rule) and posts one ledger entry per rule that awards something —
     * each with its own idempotency key
     * (`order_points:{shop_id}:{order_id}:{rule_id}`) so two different
     * rules' awards for the same order are independently idempotent,
     * never collapsed into or confused with each other.
     */
    public function accrueForOrder(Customer $customer, Order $order): Collection
    {
        $posted = collect();

        foreach ($this->evaluableRulesFor($customer, 'points_per_dollar') as $rule) {
            $basePoints = $this->calculator->calculateForOrder($rule, $order);

            if ($basePoints <= 0) {
                continue;
            }

            // VIP tier points multiplier — see VipBenefitService's
            // docblock and docs/VIP_TIERS.md's points-integration
            // section. Applied here (not inside PointCalculationService,
            // which is deliberately VIP-agnostic and only knows about a
            // rule's own config) because the multiplier is a property
            // of the CUSTOMER's tier, not the point rule.
            $multiplier = $this->vipBenefits->pointsMultiplierFor($customer);
            $points = (int) floor($basePoints * $multiplier);

            $posted->push($this->ledger->post(
                customer: $customer,
                direction: PointTransaction::DIRECTION_EARN,
                points: $points,
                source: PointTransaction::SOURCE_PURCHASE,
                sourceReferenceType: Order::class,
                sourceReferenceId: $order->id,
                note: "Order #{$order->order_number}",
                metadata: ['rule_id' => $rule->id, 'order_total_cents' => $order->total_cents, 'base_points' => $basePoints, 'vip_multiplier' => $multiplier],
                expiresAt: $this->expiryDateFor($customer),
                pointRuleId: $rule->id,
                idempotencyKey: "order_points:{$customer->shop_id}:{$order->id}:{$rule->id}",
            ));
        }

        return $posted;
    }

    /**
     * Awards the one-time account-creation bonus — see
     * `App\Listeners\Points\AwardAccountCreationPoints`, fired off the
     * existing `CustomerEnrolled` event. Idempotent via
     * `account_creation:{shop_id}:{customer_id}` regardless of how many
     * active `signup_bonus` rules exist (only the FIRST is applied — a
     * customer gets one signup bonus, not one per configured rule,
     * unlike purchase rules which deliberately stack).
     */
    public function accrueSignupBonus(Customer $customer): ?PointTransaction
    {
        $rule = $this->evaluableRulesFor($customer, 'signup_bonus')->first();
        if (! $rule) {
            return null;
        }

        $points = (int) ($rule->config['bonus_points'] ?? 0);
        if ($points <= 0) {
            return null;
        }

        return $this->ledger->post(
            customer: $customer,
            direction: PointTransaction::DIRECTION_EARN,
            points: $points,
            source: PointTransaction::SOURCE_ACCOUNT_CREATION_REWARD,
            note: 'Welcome bonus for joining the loyalty program.',
            expiresAt: $this->expiryDateFor($customer),
            pointRuleId: $rule->id,
            idempotencyKey: "account_creation:{$customer->shop_id}:{$customer->id}",
        );
    }

    /**
     * Awards the birthday bonus — see `App\Jobs\Points\AwardBirthdayPointsJob`,
     * scheduled daily. Idempotent per calendar YEAR
     * (`birthday_reward:{shop_id}:{customer_id}:{year}`) — "one reward
     * per customer per year," exactly as required; running the
     * scheduled job on the customer's birthday every year naturally
     * produces one award per key per year without any extra
     * "already awarded this year" bookkeeping beyond the ledger itself.
     */
    public function accrueBirthdayBonus(Customer $customer, int $year): ?PointTransaction
    {
        $rule = $this->evaluableRulesFor($customer, 'birthday_bonus')->first();
        if (! $rule) {
            return null;
        }

        $points = (int) ($rule->config['bonus_points'] ?? 0);
        if ($points <= 0) {
            return null;
        }

        return $this->ledger->post(
            customer: $customer,
            direction: PointTransaction::DIRECTION_EARN,
            points: $points,
            source: PointTransaction::SOURCE_BIRTHDAY_REWARD,
            note: "Happy birthday from {$customer->shop->name}!",
            expiresAt: $this->expiryDateFor($customer),
            pointRuleId: $rule->id,
            idempotencyKey: "birthday_reward:{$customer->shop_id}:{$customer->id}:{$year}",
        );
    }

    /**
     * Referral reward posting — the ledger-integration point
     * `App\Listeners\Referrals\RewardReferrerOnCompletion` (stubbed in
     * an earlier milestone) will call once the full referral system is
     * built. Deliberately implemented now (it's a thin, generic wrapper
     * exactly like the other accrual methods) even though nothing calls
     * it yet — per this task's explicit instruction not to build the
     * final referral system, but the ledger/idempotency plumbing for it
     * is the same shape as every other reward type, so defining it here
     * costs nothing and avoids a second, inconsistent implementation
     * later.
     */
    /**
     * Referral reward posting — called by `ReferralRewardService` (see
     * docs/REFERRAL_PROGRAM.md) for BOTH sides of a double-sided
     * referral: the referrer's payout (`$source` defaults to
     * `SOURCE_REFERRAL_REWARD`) and, separately, the referee's signup
     * bonus (`$source = SOURCE_REFERRAL_BONUS`) — two distinct calls,
     * two distinct idempotency keys (naturally differentiated since
     * `$customer` differs between the two calls), never conflated in
     * reporting despite sharing this one orchestration method.
     */
    public function accrueReferralReward(Customer $customer, int $referralId, int $points, string $source = PointTransaction::SOURCE_REFERRAL_REWARD): ?PointTransaction
    {
        if ($points <= 0) {
            return null;
        }

        return $this->ledger->post(
            customer: $customer,
            direction: PointTransaction::DIRECTION_EARN,
            points: $points,
            source: $source,
            sourceReferenceType: Referral::class,
            sourceReferenceId: $referralId,
            note: $source === PointTransaction::SOURCE_REFERRAL_BONUS ? 'Welcome bonus for joining via a referral.' : 'Referral reward.',
            expiresAt: $this->expiryDateFor($customer),
            idempotencyKey: "referral_reward:{$customer->shop_id}:{$referralId}:{$customer->id}",
        );
    }

    public function evaluableRulesFor(Customer $customer, string $type): Collection
    {
        return $this->rules->activeForShop($customer->shop)
            ->filter(fn (PointRule $r) => $r->type === $type);
    }

    /** Reads the shop's expiry policy (shop_settings.points_expiry_days) — null means points never expire. */
    private function expiryDateFor(Customer $customer): ?\DateTimeInterface
    {
        $days = $customer->shop->setting?->points_expiry_days;

        return $days ? now()->addDays($days) : null;
    }
}
