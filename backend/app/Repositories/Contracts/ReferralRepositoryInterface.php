<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use App\Models\Referral;
use App\Models\Shop;

interface ReferralRepositoryInterface
{
    public function paginateForShop(Shop $shop, int $perPage = 25, ?string $status = null);

    public function paginateForCustomer(Customer $customer, int $perPage = 25);

    public function findPendingByCode(Shop $shop, string $code): ?Referral;

    public function create(array $attributes): Referral;

    public function countCompletedForCustomer(Customer $customer): int;

    public function statisticsFor(Shop $shop): array;
}
