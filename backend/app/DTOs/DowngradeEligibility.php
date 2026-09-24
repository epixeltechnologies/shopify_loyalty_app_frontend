<?php

namespace App\DTOs;

/**
 * Result of PlanService::checkDowngradeEligibility() — a structured,
 * frontend-renderable answer to "can this shop safely move to this
 * plan," rather than a bare boolean.
 *
 * Two distinct severities, deliberately not conflated:
 *   - `blockers`: measurable usage that concretely exceeds the target
 *     plan's limits (customer count, campaign count, a VIP tier
 *     actively configured that the target doesn't support) — the
 *     downgrade is refused until resolved.
 *   - `warnings`: a feature the current plan has that the target
 *     doesn't (CSV export, full API access, advanced notifications) —
 *     surfaced so the merchant isn't silently surprised, but NOT
 *     blocking, since this app has no per-feature usage log and can't
 *     actually prove the merchant is relying on it. Blocking on every
 *     feature diff would make a Professional -> Starter downgrade
 *     effectively always refused, which isn't the intent.
 *
 * Each entry is both a stable `code` (for the frontend to key off of /
 * tests to assert on) and a human-readable `message` (safe to render
 * directly, no client-side string composition needed).
 */
final class DowngradeEligibility
{
    /**
     * @param  array<int, array{code: string, message: string}>  $blockers
     * @param  array<int, array{code: string, message: string}>  $warnings
     */
    public function __construct(
        public readonly bool $eligible,
        public readonly array $blockers = [],
        public readonly array $warnings = [],
    ) {}

    /** @param array<int, array{code: string, message: string}> $warnings */
    public static function allowed(array $warnings = []): self
    {
        return new self(eligible: true, warnings: $warnings);
    }

    /**
     * @param  array<int, array{code: string, message: string}>  $blockers
     * @param  array<int, array{code: string, message: string}>  $warnings
     */
    public static function blocked(array $blockers, array $warnings = []): self
    {
        return new self(eligible: false, blockers: $blockers, warnings: $warnings);
    }

    public function toArray(): array
    {
        return ['eligible' => $this->eligible, 'blockers' => $this->blockers, 'warnings' => $this->warnings];
    }
}
