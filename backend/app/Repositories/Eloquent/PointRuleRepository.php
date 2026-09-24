<?php

namespace App\Repositories\Eloquent;

use App\Models\PointRule;
use App\Models\Shop;
use App\Repositories\Contracts\PointRuleRepositoryInterface;
use Illuminate\Support\Collection;

class PointRuleRepository implements PointRuleRepositoryInterface
{
    public function paginateForShop(Shop $shop, int $perPage = 25)
    {
        return PointRule::query()
            ->where('shop_id', $shop->id)
            ->latest()
            ->paginate($perPage);
    }

    public function activeForShop(Shop $shop): Collection
    {
        return PointRule::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'active')
            ->get();
    }

    public function create(array $attributes): PointRule
    {
        return PointRule::query()->create($attributes);
    }

    public function countActive(Shop $shop): int
    {
        return PointRule::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'active')
            ->count();
    }
}
