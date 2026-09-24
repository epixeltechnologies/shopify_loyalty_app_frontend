<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Models\Shop;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerRepository implements CustomerRepositoryInterface
{
    /** Whitelisted sort columns — never interpolate the raw `sort` query param directly into SQL. */
    private const SORTABLE_COLUMNS = ['created_at', 'enrolled_at', 'first_name', 'last_name', 'email'];

    public function paginateForShop(Shop $shop, int $perPage = 25, array $filters = []): LengthAwarePaginator
    {
        $query = Customer::query()
            ->where('shop_id', $shop->id)
            ->with('vipTier');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('shopify_customer_id', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['vip_tier_id'])) {
            $query->where('vip_tier_id', $filters['vip_tier_id']);
        }

        $sort = $filters['sort'] ?? '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $query->orderBy(in_array($column, self::SORTABLE_COLUMNS, true) ? $column : 'created_at', $direction);

        return $query->paginate($perPage);
    }

    public function findByShopifyCustomerId(Shop $shop, string $shopifyCustomerId): ?Customer
    {
        return Customer::query()
            ->where('shop_id', $shop->id)
            ->where('shopify_customer_id', $shopifyCustomerId)
            ->first();
    }

    public function findByReferralCode(Shop $shop, string $referralCode): ?Customer
    {
        return Customer::query()
            ->where('shop_id', $shop->id)
            ->where('referral_code', $referralCode)
            ->first();
    }

    public function create(array $attributes): Customer
    {
        return Customer::query()->create($attributes);
    }

    public function countActive(Shop $shop): int
    {
        return Customer::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'active')
            ->count();
    }
}
