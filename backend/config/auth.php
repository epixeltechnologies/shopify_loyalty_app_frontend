<?php

return [
    /*
    |--------------------------------------------------------------------
    | Authentication foundation
    |--------------------------------------------------------------------
    | This app has TWO distinct authenticated actors, deliberately not
    | unified into one guard:
    |   - Merchants (embedded app requests) authenticate via a Shopify
    |     App Bridge session token JWT, verified by
    |     VerifyShopifySessionToken — NOT through this guard system at
    |     all. There is no "merchant user" Eloquent model; the
    |     authenticated identity is the Shop, resolved into
    |     TenantContext (see docs/MULTI_TENANCY.md).
    |   - Shop STAFF (the `users` table — dashboard accounts with roles/
    |     permissions, see docs/DATABASE.md) use the standard 'web' guard
    |     below, for the staff-accounts milestone
    |     (docs/NEXT_STEPS.md step 9). Session-based, not token-based,
    |     since staff logins happen through a normal login form, not an
    |     embedded iframe.
    | The 'api' guard exists for Sanctum-issued personal access tokens
    | (config/sanctum.php), reserved for the future public API
    | (feature: api.full_access, Professional plan) — see
    | docs/NEXT_STEPS.md and the commented `public-api` route group.
    */
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
