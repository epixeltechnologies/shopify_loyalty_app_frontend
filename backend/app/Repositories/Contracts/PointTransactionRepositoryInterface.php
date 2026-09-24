<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use App\Models\PointTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PointTransactionRepositoryInterface
{
    public function paginateForCustomer(Customer $customer, int $perPage = 25): LengthAwarePaginator;

    public function create(array $attributes): PointTransaction;

    public function sumUnexpiredEarnedSince(Customer $customer, \DateTimeInterface $since): int;
}
