<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Collection Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix for all collections created by Velo.
    | ! Changing this on runtime will cause issues with existing collections.
    |
    */
    'collection_prefix' => env('VELO_COLLECTION_PREFIX', '_velo_'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Records Per Page
    |--------------------------------------------------------------------------
    |
    | The maximum number of records to return per page.
    |
    */
    'records_per_page_max' => env('VELO_RECORDS_PER_PAGE_MAX', 500),

    /*
    |--------------------------------------------------------------------------
    | Default Auth Collection
    |--------------------------------------------------------------------------
    |
    | The name of the default authentication collection.
    | Used to create the default users table and
    | point the collection to the physical table.
    |
    | ! Changing this on runtime will cause issues with existing collections.
    |
    */
    'default_auth_collection' => env('VELO_DEFAULT_AUTH_COLLECTION', 'users'),

    /*
    |--------------------------------------------------------------------------
    | OTP Configuration
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'length' => (int) env('VELO_OTP_LENGTH', 6),
        'ttl' => (int) env('VELO_OTP_TTL', 15),
        'cleanup_grace' => (int) env('VELO_OTP_CLEANUP_GRACE', 60),
    ],

    'realtime' => [
        'bus' => env('VELO_REALTIME_BUS', 'redis'),
        'mode' => env('VELO_REALTIME_MODE', 'persistent'),
        'cron_ttl' => env('VELO_REALTIME_TTL', 55),
        'subscription_ttl' => env('VELO_REALTIME_SUBSCRIPTION_TTL', 120),
    ],
];
