<?php

namespace App\Services\Rewards\DiscountTypes;

use App\Models\Reward;

/**
 * The extensibility point the task explicitly asks for: "future reward
 * types should be addable without rewriting the core redemption
 * system." Adding a new discount type means writing one class that
 * implements this interface and registering it in `AppServiceProvider`
 * — `ShopifyDiscountService` never needs to change, and neither does
 * `RewardRedemptionService` or any of the redemption flow's validation/
 * locking logic, all of which are reward-type-agnostic by design.
 */
interface RewardDiscountTypeInterface
{
    /** Must match one of `Reward::TYPE_*`. */
    public function supports(string $rewardType): bool;

    /**
     * Builds the `customerGets` fragment of Shopify's discount-code
     * creation mutation — the part that actually varies by discount
     * type. Everything else (title, code, customer targeting, usage
     * limits, date window, minimum requirement) is assembled once, the
     * same way for every type, by ShopifyDiscountService.
     *
     * @return array<string, mixed>
     */
    public function buildCustomerGets(Reward $reward): array;

    /** Which GraphQL mutation this type is created through — Shopify uses a different top-level mutation for free shipping vs. basic (fixed/percentage) codes. */
    public function mutationName(): string;
}
