<?php

namespace App\Policies;

use App\Models\AnalyticsExport;
use App\Support\Tenancy\TenantContext;

class AnalyticsExportPolicy
{
    public function view(mixed $user, AnalyticsExport $export): bool
    {
        return TenantContext::hasShop() && $export->shop_id === TenantContext::shopId();
    }
}
