<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    /**
     * @param  array{search?: string, status?: string, vip_tier_id?: int, sort?: string}  $filters
     */
    public function paginateForShop(Shop $shop, int $perPage = 25, array $filters = []): LengthAwarePaginator;

    public function findByShopifyCustomerId(Shop $shop, string $shopifyCustomerId): ?Customer;

    public function findByReferralCode(Shop $shop, string $referralCode): ?Customer;

    public function create(array $attributes): Customer;

    public function countActive(Shop $shop): int;
}
