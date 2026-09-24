<?php

namespace App\Jobs\Shopify;

use App\Jobs\Concerns\HasDefaultRetryPolicy;
use App\Models\Shop;
use App\Services\Shopify\WebhookRegistrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by ShopifyOAuthService after every successful install AND
 * reinstall — Shopify does not carry webhook subscriptions forward
 * across an uninstall, so this always runs rather than only on first
 * install. See WebhookRegistrationService for the idempotency
 * guarantee that makes re-running this safe.
 */
class RegisterShopWebhooksJob implements ShouldQueue
{
    use Dispatchable, HasDefaultRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Shop $shop) {}

    public function handle(WebhookRegistrationService $registrar): void
    {
        $registrar->registerAll($this->shop);
    }
}
