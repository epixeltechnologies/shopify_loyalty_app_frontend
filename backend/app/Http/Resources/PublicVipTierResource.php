<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer-facing counterpart to VipTierResource — never the
 * qualification method's raw configuration internals a merchant edits
 * (evaluation_period plumbing, plan-availability flags), matching the
 * task's "do not expose internal configuration unnecessarily." A
 * customer needs to know what a tier requires and what it gives them,
 * not how the admin's qualification engine is wired.
 *
 * @mixin \App\Models\VipTier
 */
class PublicVipTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'benefits' => $this->perks,
        ];
    }
}
