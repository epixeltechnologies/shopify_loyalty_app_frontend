<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SubscriptionEvent */
class SubscriptionEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'from_plan' => $this->whenLoaded('fromPlan', fn () => $this->fromPlan?->name),
            'to_plan' => $this->whenLoaded('toPlan', fn () => $this->toPlan?->name),
            'trigger' => $this->trigger,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
