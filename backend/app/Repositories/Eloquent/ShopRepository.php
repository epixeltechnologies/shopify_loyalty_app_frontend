<?php

namespace App\Repositories\Eloquent;

use App\Models\Shop;
use App\Repositories\Contracts\ShopRepositoryInterface;
use Illuminate\Support\Collection;

class ShopRepository implements ShopRepositoryInterface
{
    public function findByDomain(string $domain): ?Shop
    {
        return Shop::query()->where('shopify_domain', $domain)->first();
    }

    public function findOrFailByDomain(string $domain): Shop
    {
        return Shop::query()->where('shopify_domain', $domain)->firstOrFail();
    }

    public function create(array $attributes): Shop
    {
        return Shop::query()->create($attributes);
    }

    public function markUninstalled(Shop $shop): Shop
    {
        $shop->update([
            'is_installed' => false,
            'uninstalled_at' => now(),
            'access_token' => null,
        ]);

        return $shop->fresh();
    }

    public function activeShops(): Collection
    {
        return Shop::query()->where('is_installed', true)->get();
    }
}
