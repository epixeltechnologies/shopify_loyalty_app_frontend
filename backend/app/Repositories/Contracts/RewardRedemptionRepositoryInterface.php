<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use App\Models\RewardRedemption;
use App\Models\Shop;

interface RewardRedemptionRepositoryInterface
{
    public function paginateForCustomer(Customer $customer, int $perPage = 25);

    public function paginateForShop(Shop $shop, int $perPage = 25);

    public function create(array $attributes): RewardRedemption;

    /** Shop-wide redemption statistics for the merchant admin dashboard. */
    public function statisticsFor(Shop $shop): array;
}
