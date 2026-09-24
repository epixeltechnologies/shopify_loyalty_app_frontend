<?php

use Illuminate\Support\Str;

return [
    /*
    |--------------------------------------------------------------------
    | Horizon Domain / Path / Redis Connection
    |--------------------------------------------------------------------
    */
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',

    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'loyalty'), '_').'_horizon:'),

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------
    | Failed / recent job retention
    |--------------------------------------------------------------------
    | Horizon prunes its own Redis-backed job metrics independent of the
    | `failed_jobs` table (which is permanent). These are display/metrics
    | retention only.
    */
    'trim' => [
        'recent' => 60,           // minutes
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080, // 7 days
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],

    'metrics' => [
        'trim_snapshots' => ['job' => 24, 'queue' => 24],
    ],

    'fast_termination' => false,

    'memory_limit' => 128,

    /*
    |--------------------------------------------------------------------
    | Queue worker configuration per environment
    |--------------------------------------------------------------------
    | The `webhooks` queue gets its own supervisor with a higher process
    | count and shorter timeout — webhook jobs must stay fast (Shopify
    | expects prompt processing) and a burst of webhook deliveries should
    | never starve the `default` queue that user-facing actions
    | (points adjustments, reward redemptions) may eventually share.
    */
    'defaults' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 3,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
        'supervisor-webhooks' => [
            'connection' => 'redis',
            'queue' => ['webhooks'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 5,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 5,
            'timeout' => 30,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-default' => ['maxProcesses' => 10, 'balanceMaxShift' => 1, 'balanceCooldown' => 3],
            'supervisor-webhooks' => ['maxProcesses' => 15, 'balanceMaxShift' => 1, 'balanceCooldown' => 3],
        ],

        'local' => [
            'supervisor-default' => ['maxProcesses' => 2],
            'supervisor-webhooks' => ['maxProcesses' => 2],
        ],
    ],
];
