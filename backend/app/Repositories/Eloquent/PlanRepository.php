<?php

namespace App\Repositories\Eloquent;

use App\Models\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Support\Cache\CacheKeys;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PlanRepository implements PlanRepositoryInterface
{
    public function all(): Collection
    {
        return Cache::remember(CacheKeys::activePlans(), CacheKeys::TTL_MEDIUM, fn () => Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with('features')
            ->get()
        );
    }

    public function findBySlug(string $slug): ?Plan
    {
        return Plan::query()->where('slug', $slug)->first();
    }

    public function findByShopifyHandle(string $handle): ?Plan
    {
        return Plan::query()->where('shopify_plan_handle', $handle)->first();
    }

    public function default(): ?Plan
    {
        return Plan::query()->where('is_default', true)->first();
    }
}
