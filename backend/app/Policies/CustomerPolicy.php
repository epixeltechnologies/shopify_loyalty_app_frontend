<?php

namespace App\Policies;

use App\Models\Customer;
use App\Support\Tenancy\TenantContext;

/**
 * Every ability first re-asserts tenant ownership — belt-and-suspenders
 * on top of TenantScope, since policies run against a specific model
 * instance that may have been resolved via implicit route-model binding
 * before any tenant-scoped query filter had a chance to apply.
 */
class CustomerPolicy
{
    public function view(mixed $user, Customer $customer): bool
    {
        return $this->belongsToCurrentTenant($customer);
    }

    public function adjust(mixed $user, Customer $customer): bool
    {
        return $this->belongsToCurrentTenant($customer);
    }

    public function redeem(mixed $user, Customer $customer): bool
    {
        return $this->belongsToCurrentTenant($customer);
    }

    private function belongsToCurrentTenant(Customer $customer): bool
    {
        return TenantContext::hasShop() && $customer->shop_id === TenantContext::shopId();
    }
}
