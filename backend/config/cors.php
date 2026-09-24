<?php

return [
    /*
    |--------------------------------------------------------------------
    | CORS foundation
    |--------------------------------------------------------------------
    | The embedded app itself never needs CORS — it's server-rendered
    | (resources/views/app.blade.php) and same-origin from the browser's
    | perspective except for the local Vite dev server. This config
    | exists for: (1) local development, where the frontend runs on a
    | different port (5173) than the API (8000), and (2) the future
    | public API (feature: api.full_access) that external integrations
    | will call cross-origin.
    */
    'paths' => ['api/*', 'webhooks/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 0,

    'supports_credentials' => false,
];
