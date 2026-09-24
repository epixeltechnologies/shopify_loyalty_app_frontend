<?php

return [
    'default' => env('CACHE_STORE', 'redis'),

    'stores' => [
        'array' => ['driver' => 'array', 'serialize' => false],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'cache',
            'lock_connection' => null,
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'shopify_loyalty_cache_'),
];
