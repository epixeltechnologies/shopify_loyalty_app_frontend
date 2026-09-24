<?php

return [
    /*
    |--------------------------------------------------------------------
    | Shopify App Credentials
    |--------------------------------------------------------------------
    */
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'api_version' => env('SHOPIFY_API_VERSION', '2025-01'),

    'app_url' => env('APP_URL'),

    /*
    |--------------------------------------------------------------------
    | OAuth
    |--------------------------------------------------------------------
    */
    'scopes' => env('SHOPIFY_SCOPES', implode(',', [
        'read_customers',
        'write_customers',
        'read_orders',
        'read_products',
        'read_discounts',
        'write_discounts',
        'read_price_rules',
        'write_price_rules',
        'read_script_tags',
        'write_script_tags',
    ])),

    'oauth' => [
        'authorize_path' => '/auth',
        'callback_path' => '/auth/callback',
        // How long a generated OAuth `state` value remains valid — see
        // App\Services\Shopify\OAuthStateService. Kept short: this only
        // needs to survive the redirect to Shopify and back, typically
        // seconds, not the merchant's whole session.
        'state_ttl' => (int) env('SHOPIFY_OAUTH_STATE_TTL', 600),
    ],

    /*
    |--------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------
    | Topic => Job class handling the webhook payload. Every job extends
    | App\Jobs\Webhooks\WebhookJob — see docs/WEBHOOKS.md for the full
    | flow (verification, idempotency, retry). Only topics this app
    | actually acts on are listed here — WebhookRegistrationService
    | registers exactly this set (minus the three compliance topics,
    | configured via the Partner Dashboard instead — see that service's
    | docblock), so adding an unused topic here would also register it
    | against every shop for no reason.
    */
    'webhooks' => [
        // Compliance (mandatory, GDPR) — registered via Partner Dashboard, not the API.
        'customers/data_request' => \App\Jobs\Webhooks\HandleCustomerDataRequestJob::class,
        'customers/redact' => \App\Jobs\Webhooks\HandleCustomerRedactJob::class,
        'shop/redact' => \App\Jobs\Webhooks\HandleShopRedactJob::class,

        // Lifecycle
        'app/uninstalled' => \App\Jobs\Webhooks\HandleAppUninstalledJob::class,
        'app_subscriptions/update' => \App\Jobs\Webhooks\HandleSubscriptionUpdatedJob::class,
        'shop/update' => \App\Jobs\Webhooks\HandleShopUpdatedJob::class,

        // Customers
        'customers/create' => \App\Jobs\Webhooks\HandleCustomerCreatedJob::class,
        'customers/update' => \App\Jobs\Webhooks\HandleCustomerUpdatedJob::class,
        'customers/delete' => \App\Jobs\Webhooks\HandleCustomerDeletedJob::class,

        // Orders — reliable event capture only; no points calculation
        // yet, see docs/NEXT_STEPS.md and each job's docblock.
        'orders/create' => \App\Jobs\Webhooks\HandleOrderCreatedJob::class,
        'orders/updated' => \App\Jobs\Webhooks\HandleOrderUpdatedJob::class,
        'orders/cancelled' => \App\Jobs\Webhooks\HandleOrderCancelledJob::class,
        'refunds/create' => \App\Jobs\Webhooks\HandleRefundCreatedJob::class,
    ],

    'webhook_verification' => [
        'header' => 'X-Shopify-Hmac-Sha256',
        'tolerate_clock_skew_seconds' => 300,
    ],

    /*
    |--------------------------------------------------------------------
    | Billing (Shopify Managed Pricing)
    |--------------------------------------------------------------------
    | Managed Pricing plans are configured in the Shopify Partner Dashboard.
    | This app mirrors the resulting entitlements locally via the
    | plans / plan_features tables (see Plan model)
    | so that feature gating never depends on a live API call.
    */
    'billing' => [
        'test_mode' => env('SHOPIFY_BILLING_TEST_MODE', true),
        'return_url' => env('APP_URL').'/billing/callback',
        'trial_days' => (int) env('SHOPIFY_TRIAL_DAYS', 0),
        'currency' => env('SHOPIFY_BILLING_CURRENCY', 'USD'),
        // Managed Pricing plan handles must match the Partner Dashboard exactly.
        'plan_handles' => [
            'starter' => env('SHOPIFY_PLAN_HANDLE_STARTER', 'starter'),
            'professional' => env('SHOPIFY_PLAN_HANDLE_PROFESSIONAL', 'professional'),
        ],
    ],

    /*
    |--------------------------------------------------------------------
    | Rate limiting for outbound Admin API calls
    |--------------------------------------------------------------------
    */
    'api_rate_limit' => [
        'bucket_size' => 40,
        'leak_rate_per_second' => 2,
        'max_retries' => 5,
    ],
];
