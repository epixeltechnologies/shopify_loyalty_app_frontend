<?php

use Illuminate\Support\Str;

return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION', 'default'),
    'table' => 'sessions',
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],

    'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'loyalty'), '_').'_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),

    // This app is embedded in a cross-origin iframe (Shopify Admin), so
    // cookies must be SameSite=None + Secure wherever a session cookie
    // is actually used (the future staff-login flow) — 'lax' would
    // silently fail to be sent inside the iframe.
    'secure' => env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => 'none',
    'partitioned' => false,
];
