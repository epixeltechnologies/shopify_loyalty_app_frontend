<?php

namespace App\Rules;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Validates that an ID submitted in a request BODY (as opposed to a
 * route parameter, which is already tenant-safe via implicit
 * route-model binding + TenantScope — see docs/MULTI_TENANCY.md)
 * belongs to the current tenant. Needed anywhere a form accepts a
 * foreign-key-like value directly, e.g. a future "move this customer to
 * vip_tier_id X" request — without this, a shop could reference another
 * shop's row ID and (depending on what the receiving code does with it)
 * leak cross-tenant data or attach the wrong relationship.
 *
 * Usage: 'vip_tier_id' => ['required', new BelongsToCurrentShop(VipTier::class)]
 */
class BelongsToCurrentShop implements ValidationRule
{
    public function __construct(private readonly string $modelClass) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! TenantContext::hasShop()) {
            $fail('Unable to verify the :attribute — no tenant resolved.');

            return;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass;

        $exists = $modelClass::query()
            ->whereKey($value)
            ->where('shop_id', TenantContext::shopId())
            ->exists();

        if (! $exists) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
