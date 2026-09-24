<?php

namespace App\Exceptions\Billing;

use RuntimeException;

/**
 * Thrown by any service enforcing a plan usage limit (customer count,
 * reward-campaign count, ...) — caught centrally by
 * App\Exceptions\Handlers\ApiExceptionRenderer and rendered as a
 * structured `LIMIT_REACHED` response (feature, current usage, limit,
 * required plan, upgrade action), never a bare "403 Forbidden" a
 * frontend has to guess the meaning of. See docs/ENTITLEMENTS.md.
 *
 * Deliberately carries only the fields safe to expose to the client —
 * no internal plan IDs, no query details, nothing beyond what the
 * upgrade-prompt UI needs to render itself.
 */
class LimitReachedException extends RuntimeException
{
    public function __construct(
        public readonly string $feature,
        public readonly int $currentUsage,
        public readonly int $limit,
        public readonly ?string $requiredPlan = null,
    ) {
        parent::__construct("The {$feature} limit ({$limit}) has been reached.");
    }

    public function toResponsePayload(): array
    {
        return array_filter([
            'feature' => $this->feature,
            'current_usage' => $this->currentUsage,
            'limit' => $this->limit,
            'required_plan' => $this->requiredPlan,
            'upgrade_action' => 'GET /api/v1/billing/plans',
        ], fn ($v) => $v !== null);
    }
}
