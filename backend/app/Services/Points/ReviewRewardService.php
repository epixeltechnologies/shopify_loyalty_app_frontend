<?php

namespace App\Services\Points;

use App\Models\Customer;
use App\Models\PointTransaction;

/**
 * POINT SYSTEM: a provider-agnostic entry point for "a customer left a
 * review, award them points" — deliberately NOT wired to any specific
 * review platform (Judge.me, Loox, Yotpo, Shopify's own product
 * reviews, ...) since none is a confirmed integration yet; wiring one
 * means adding a webhook route/job that calls `recordReview()`, not
 * changing this class. This is the abstraction the task asks for: the
 * point-awarding half of "review reward" is fully implemented and
 * tested; the review-PLATFORM half is an integration this class is
 * ready to receive whenever one is chosen.
 *
 * `$externalReviewId` is whatever unique identifier the eventual
 * provider's payload supplies (a review ID, not the same thing as
 * `$productId`) — it's what the idempotency key is built from, so the
 * SAME review event calling this twice (a provider's own webhook
 * retry, unrelated to this app's queue) never double-awards.
 */
class ReviewRewardService
{
    public function __construct(private readonly PointsAccrualService $accrual) {}

    public function recordReview(Customer $customer, string $externalReviewId, ?string $productId = null): ?PointTransaction
    {
        $rule = $this->accrual->evaluableRulesFor($customer, 'review_reward')->first();
        if (! $rule) {
            return null;
        }

        $points = (int) ($rule->config['bonus_points'] ?? 0);
        if ($points <= 0) {
            return null;
        }

        return app(PointsLedgerService::class)->post(
            customer: $customer,
            direction: PointTransaction::DIRECTION_EARN,
            points: $points,
            source: PointTransaction::SOURCE_REVIEW_REWARD,
            note: $productId ? "Review reward for product {$productId}." : 'Review reward.',
            metadata: array_filter(['external_review_id' => $externalReviewId, 'product_id' => $productId]),
            pointRuleId: $rule->id,
            idempotencyKey: "review_reward:{$customer->shop_id}:{$externalReviewId}",
        );
    }
}
