<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Repositories\Contracts\PointTransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PointTransactionRepository implements PointTransactionRepositoryInterface
{
    public function paginateForCustomer(Customer $customer, int $perPage = 25): LengthAwarePaginator
    {
        return PointTransaction::query()
            ->where('customer_id', $customer->id)
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $attributes): PointTransaction
    {
        return PointTransaction::query()->create($attributes);
    }

    public function sumUnexpiredEarnedSince(Customer $customer, \DateTimeInterface $since): int
    {
        return (int) PointTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('direction', PointTransaction::DIRECTION_EARN)
            ->where('created_at', '>=', $since)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->sum('points');
    }
}
