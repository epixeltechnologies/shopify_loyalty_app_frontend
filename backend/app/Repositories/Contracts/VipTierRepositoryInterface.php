<?php

namespace App\Repositories\Contracts;

use App\Models\Shop;
use App\Models\VipTier;
use Illuminate\Support\Collection;

interface VipTierRepositoryInterface
{
    public function allForShop(Shop $shop): Collection;

    public function findBySlug(Shop $shop, string $slug): ?VipTier;

    /** Highest tier whose threshold the given point total qualifies for. */
    public function resolveForPoints(Shop $shop, int $lifetimePoints): ?VipTier;
}
