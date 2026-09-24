<?php

namespace App\Repositories\Contracts;

use App\Models\Shop;
use Illuminate\Support\Collection;

interface ShopRepositoryInterface
{
    public function findByDomain(string $domain): ?Shop;

    public function findOrFailByDomain(string $domain): Shop;

    public function create(array $attributes): Shop;

    public function markUninstalled(Shop $shop): Shop;

    public function activeShops(): Collection;
}
