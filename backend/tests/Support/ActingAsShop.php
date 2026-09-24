<?php

namespace Tests\Support;

use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Firebase\JWT\JWT;

/**
 * Shared test helper: this app authenticates every API request via a
 * Shopify App Bridge session token JWT, not a Sanctum/session login —
 * see VerifyShopifySessionToken. Feature tests need a *valid-looking*
 * token for a given shop rather than Laravel's usual `actingAs($user)`,
 * so every Feature test that hits `/api/v1/*` uses this trait instead.
 */
trait ActingAsShop
{
    protected function actingAsShop(Shop $shop): static
    {
        TenantContext::set($shop);

        $token = JWT::encode([
            'iss' => "https://{$shop->shopify_domain}/admin",
            'dest' => "https://{$shop->shopify_domain}",
            'aud' => config('shopify.api_key'),
            'sub' => $shop->shopify_id ?? '1',
            'exp' => now()->addMinute()->timestamp,
            'nbf' => now()->subMinute()->timestamp,
            'iat' => now()->timestamp,
            'jti' => (string) \Illuminate\Support\Str::uuid(),
        ], config('shopify.api_secret'), 'HS256');

        return $this->withHeader('Authorization', "Bearer {$token}");
    }
}
