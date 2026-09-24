<?php

return [
    /*
    |--------------------------------------------------------------------
    | Third-party service credentials
    |--------------------------------------------------------------------
    | Shopify's own credentials live in config/shopify.php (they're
    | first-class to this app, not a generic "integration"). This file
    | holds everything else. `error_tracking` is the config surface
    | App\Exceptions\Handlers\ApiExceptionRenderer's reportable() hook
    | is written against — see its docblock and docs/SECURITY.md.
    */
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'error_tracking' => [
        'driver' => env('ERROR_TRACKING_DRIVER'), // null | 'sentry' | 'bugsnag' | 'flare'
        'dsn' => env('ERROR_TRACKING_DSN'),
    ],
];
