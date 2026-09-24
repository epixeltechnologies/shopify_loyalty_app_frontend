<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every tenant-scoped model. Automatically filters queries to
 * the currently resolved shop and stamps `shop_id` on create.
 *
 * @see \App\Models\Scopes\TenantScope
 */
trait BelongsToShop
{
    public static function bootBelongsToShop(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! $model->shop_id && TenantContext::hasShop()) {
                $model->shop_id = TenantContext::shopId();
            }
        });
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
