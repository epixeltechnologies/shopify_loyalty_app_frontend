<?php

namespace App\Repositories\Eloquent;

use App\Models\Reward;
use App\Models\Shop;
use App\Repositories\Contracts\RewardRepositoryInterface;

class RewardRepository implements RewardRepositoryInterface
{
    public function paginateForShop(Shop $shop, int $perPage = 25)
    {
        return Reward::query()
            ->where('shop_id', $shop->id)
            ->latest()
            ->paginate($perPage);
    }

    public function activeCatalogForShop(Shop $shop)
    {
        return Reward::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'active')
            ->orderBy('points_cost')
            ->get();
    }

    public function create(array $attributes): Reward
    {
        return Reward::query()->create($attributes);
    }
}
