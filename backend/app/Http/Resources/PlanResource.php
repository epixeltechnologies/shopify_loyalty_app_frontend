<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Plan */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'price_monthly' => round($this->price_monthly_cents / 100, 2),
            'currency' => $this->currency,
            'limits' => [
                'max_active_customers' => $this->max_active_customers,
                'max_active_point_rules' => $this->max_active_point_rules,
            ],
            'features' => $this->whenLoaded('features', fn () => $this->features
                ->groupBy('group')
                ->map(fn ($group) => $group->pluck('name'))
            ),
        ];
    }
}
