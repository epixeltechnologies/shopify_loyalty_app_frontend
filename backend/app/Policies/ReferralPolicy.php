<?php

namespace App\Policies;

use App\Models\Referral;
use App\Support\Tenancy\TenantContext;

class ReferralPolicy
{
    public function view(mixed $user, Referral $referral): bool
    {
        return $this->belongsToCurrentTenant($referral);
    }

    public function review(mixed $user, Referral $referral): bool
    {
        return $this->belongsToCurrentTenant($referral);
    }

    private function belongsToCurrentTenant(Referral $referral): bool
    {
        return TenantContext::hasShop() && $referral->shop_id === TenantContext::shopId();
    }
}
