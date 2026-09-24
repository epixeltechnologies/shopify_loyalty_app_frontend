<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer-facing counterpart to ReferralResource — never the
 * fraud status/reasons, never the referred customer's identity (a
 * referrer shouldn't see who exactly they referred beyond "someone
 * signed up"), matching the task's "do not expose sensitive fraud
 * information." A deliberately separate, smaller resource for the same
 * structural reason as PublicRewardRedemptionResource in the rewards
 * engine — see docs/REWARDS_ENGINE.md.
 *
 * @mixin \App\Models\Referral
 */
class PublicReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->publicStatus(),
            'created_at' => $this->created_at?->toIso8601String(),
            'rewarded_at' => $this->rewarded_at?->toIso8601String(),
        ];
    }

    /** Collapses internal states a customer doesn't need to distinguish (fraud review looks identical to "still pending" from the outside). */
    private function publicStatus(): string
    {
        return match ($this->status) {
            'rewarded' => 'rewarded',
            'rejected', 'cancelled', 'expired' => 'not_completed',
            default => 'pending',
        };
    }
}
