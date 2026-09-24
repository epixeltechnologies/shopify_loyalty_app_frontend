<?php

namespace App\Providers;

use App\Services\Shopify\ShopifyAppProxyVerifier;
use App\Services\Shopify\ShopifyGraphQLClient;
use Illuminate\Support\ServiceProvider;

class ShopifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShopifyGraphQLClient::class, fn ($app) => new ShopifyGraphQLClient(
            apiVersion: config('shopify.api_version'),
        ));

        $this->app->singleton(ShopifyAppProxyVerifier::class, fn () => new ShopifyAppProxyVerifier(
            apiSecret: config('shopify.api_secret'),
        ));
    }

    public function boot(): void {}
}
