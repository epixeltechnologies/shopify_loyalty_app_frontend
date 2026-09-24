<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Referral */
class ReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'referral_code' => $this->referral_code,
            'status' => $this->status,
            'fraud_status' => $this->fraud_status,
            'fraud_reasons' => $this->fraud_reasons,
            'referrer' => new CustomerResource($this->whenLoaded('referrer')),
            'referred' => new CustomerResource($this->whenLoaded('referred')),
            'qualifying_order_id' => $this->qualifying_order_id,
            'qualifying_order_value_cents' => $this->qualifying_order_value_cents,
            'rejection_reason' => $this->rejection_reason,
            'clicked_at' => $this->clicked_at?->toIso8601String(),
            'registered_at' => $this->registered_at?->toIso8601String(),
            'qualified_at' => $this->qualified_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'reward_scheduled_at' => $this->reward_scheduled_at?->toIso8601String(),
            'rewarded_at' => $this->rewarded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
