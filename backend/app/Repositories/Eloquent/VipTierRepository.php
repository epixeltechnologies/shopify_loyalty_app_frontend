<?php

namespace App\Repositories\Eloquent;

use App\Models\Shop;
use App\Models\VipTier;
use App\Repositories\Contracts\VipTierRepositoryInterface;
use Illuminate\Support\Collection;

class VipTierRepository implements VipTierRepositoryInterface
{
    public function allForShop(Shop $shop): Collection
    {
        return VipTier::query()
            ->where('shop_id', $shop->id)
            ->withCount('customers')
            ->orderBy('sort_order')
            ->get();
    }

    public function findBySlug(Shop $shop, string $slug): ?VipTier
    {
        return VipTier::query()
            ->where('shop_id', $shop->id)
            ->where('slug', $slug)
            ->first();
    }

    public function resolveForPoints(Shop $shop, int $lifetimePoints): ?VipTier
    {
        return VipTier::query()
            ->where('shop_id', $shop->id)
            ->where('is_active', true)
            ->where('threshold_points', '<=', $lifetimePoints)
            ->orderByDesc('threshold_points')
            ->first();
    }
}
