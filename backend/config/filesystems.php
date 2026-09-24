<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // Used in production for anything a merchant or shopper needs a
        // durable, CDN-fronted URL for: CSV exports (feature:
        // export.csv), and any custom widget assets. Local disk is only
        // safe for ephemeral files — it doesn't survive a redeploy on
        // most hosting targets. See docs/PRODUCTION.md.
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'), // set for S3-compatible providers (R2, Spaces, MinIO)
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        // Database backups — see docs/BACKUP_AND_RECOVERY.md. Deliberately
        // a SEPARATE disk/bucket from 's3' above (which serves customer-
        // facing exports/assets): a backup bucket should have its own,
        // tighter access policy (write-from-backup-host only, no public
        // read, versioning/lifecycle rules for retention — see that doc)
        // rather than sharing a bucket whose access pattern is "serve
        // files to merchants." Not configured by default — requires its
        // own bucket + credentials, which may be the same AWS account or
        // a fully separate one for stronger isolation.
        'backups' => [
            'driver' => 's3',
            'key' => env('BACKUP_AWS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('BACKUP_AWS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('BACKUP_AWS_DEFAULT_REGION', env('AWS_DEFAULT_REGION')),
            'bucket' => env('BACKUP_AWS_BUCKET'),
            'endpoint' => env('BACKUP_AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('BACKUP_AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
