<?php

namespace App\Repositories\Contracts;

use App\Models\Reward;
use App\Models\Shop;

interface RewardRepositoryInterface
{
    public function paginateForShop(Shop $shop, int $perPage = 25);

    public function activeCatalogForShop(Shop $shop);

    public function create(array $attributes): Reward;
}
