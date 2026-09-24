<?php

namespace App\Providers;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Observers\PlanConfigurationObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The reward-discount-type registry — see
        // RewardDiscountTypeInterface's docblock: this is the ONE place
        // a new reward type needs to be wired in; ShopifyDiscountService
        // itself never changes when a new type is added. Bound as an
        // explicit factory (rather than a contextual `needs()` binding)
        // since `iterable` is a pseudo-type PHP's reflection can't
        // resolve as a class/interface — `needs()` requires one.
        $this->app->bind(\App\Services\Rewards\ShopifyDiscountService::class, function ($app) {
            return new \App\Services\Rewards\ShopifyDiscountService(
                $app->make(\App\Services\Shopify\ShopifyGraphQLClient::class),
                $app->make(\App\Services\Rewards\DiscountCodeGenerator::class),
                [
                    $app->make(\App\Services\Rewards\DiscountTypes\FixedAmountDiscountType::class),
                    $app->make(\App\Services\Rewards\DiscountTypes\PercentageDiscountType::class),
                    $app->make(\App\Services\Rewards\DiscountTypes\FreeShippingDiscountType::class),
                ],
            );
        });
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! app()->isProduction());
        Model::unguard(false);

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        Plan::observe(PlanConfigurationObserver::class);
        Feature::observe(PlanConfigurationObserver::class);
        PlanFeature::observe(PlanConfigurationObserver::class);
    }
}
