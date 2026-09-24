<?php

namespace App\Policies;

use App\Models\Reward;
use App\Support\Tenancy\TenantContext;

class RewardPolicy
{
    public function view(mixed $user, Reward $reward): bool
    {
        return $this->belongsToCurrentTenant($reward);
    }

    public function update(mixed $user, Reward $reward): bool
    {
        return $this->belongsToCurrentTenant($reward);
    }

    private function belongsToCurrentTenant(Reward $reward): bool
    {
        return TenantContext::hasShop() && $reward->shop_id === TenantContext::shopId();
    }
}
