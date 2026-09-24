<?php

namespace App\Repositories\Contracts;

use App\Models\PointRule;
use App\Models\Shop;
use Illuminate\Support\Collection;

interface PointRuleRepositoryInterface
{
    public function paginateForShop(Shop $shop, int $perPage = 25);

    public function activeForShop(Shop $shop): Collection;

    public function create(array $attributes): PointRule;

    public function countActive(Shop $shop): int;
}
