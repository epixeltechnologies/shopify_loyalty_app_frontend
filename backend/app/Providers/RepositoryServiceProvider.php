<?php

namespace App\Providers;

use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\PointRuleRepositoryInterface;
use App\Repositories\Contracts\PointTransactionRepositoryInterface;
use App\Repositories\Contracts\ReferralRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Repositories\Contracts\RewardRedemptionRepositoryInterface;
use App\Repositories\Contracts\RewardRepositoryInterface;
use App\Repositories\Contracts\ShopRepositoryInterface;
use App\Repositories\Contracts\VipTierRepositoryInterface;
use App\Repositories\Eloquent\AnalyticsRepository;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Eloquent\PlanRepository;
use App\Repositories\Eloquent\PointRuleRepository;
use App\Repositories\Eloquent\PointTransactionRepository;
use App\Repositories\Eloquent\ReferralRepository;
use App\Repositories\Eloquent\RefundRepository;
use App\Repositories\Eloquent\RewardRedemptionRepository;
use App\Repositories\Eloquent\RewardRepository;
use App\Repositories\Eloquent\ShopRepository;
use App\Repositories\Eloquent\VipTierRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Binds every repository interface to its Eloquent implementation.
 * Centralized here so persistence can be swapped (read replicas, a
 * different store for one table) without touching any Service class.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    public array $bindings = [
        ShopRepositoryInterface::class => ShopRepository::class,
        PlanRepositoryInterface::class => PlanRepository::class,

        CustomerRepositoryInterface::class => CustomerRepository::class,
        PointTransactionRepositoryInterface::class => PointTransactionRepository::class,
        PointRuleRepositoryInterface::class => PointRuleRepository::class,
        VipTierRepositoryInterface::class => VipTierRepository::class,
        RewardRepositoryInterface::class => RewardRepository::class,
        RewardRedemptionRepositoryInterface::class => RewardRedemptionRepository::class,
        ReferralRepositoryInterface::class => ReferralRepository::class,
        AnalyticsRepositoryInterface::class => AnalyticsRepository::class,
        OrderRepositoryInterface::class => OrderRepository::class,
        RefundRepositoryInterface::class => RefundRepository::class,
    ];
}
