<?php

namespace App\Policies;

use App\Models\PointRule;
use App\Support\Tenancy\TenantContext;

class PointRulePolicy
{
    public function view(mixed $user, PointRule $rule): bool
    {
        return TenantContext::hasShop() && $rule->shop_id === TenantContext::shopId();
    }

    public function update(mixed $user, PointRule $rule): bool
    {
        return $this->view($user, $rule);
    }
}
