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
    | Maximum Relation Expansions
    |--------------------------------------------------------------------------
    |
    | The maximum number of relation expansions allowed in a single request.
    |
    */
    'records_expand_max' => env('VELO_RECORDS_EXPAND_MAX', 10),

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
        'numeric' => (bool) false,
        'ttl' => (int) env('VELO_OTP_TTL', 15),
        'cleanup_grace' => (int) env('VELO_OTP_CLEANUP_GRACE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Configuration
    |--------------------------------------------------------------------------
    */
    'oauth' => [
        'name_field_candidates' => ['name', 'username', 'fullname', 'full_name', 'first_name'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Realtime Configuration
    |--------------------------------------------------------------------------
    */
    'realtime' => [
        'bus' => env('VELO_REALTIME_BUS', 'redis'), // redis, filesystem
        'mode' => env('VELO_REALTIME_MODE', 'persistent'), // persistent, cron
        'cron_ttl' => env('VELO_REALTIME_TTL', 55),
        'subscription_ttl' => env('VELO_REALTIME_SUBSCRIPTION_TTL', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Collection Cache TTL
    |--------------------------------------------------------------------------
    |
    | The number of seconds to cache collection metadata.
    | Set to 0 to cache permanently.
    |
    */
    'collection_cache_ttl' => (int) env('VELO_COLLECTION_CACHE_TTL', 0),

    /*
    |--------------------------------------------------------------------------
    | Log Configuration
    |--------------------------------------------------------------------------
    */
    'logs' => [
        'slow_query_threshold' => (int) env('VELO_LOGS_SLOW_QUERY_THRESHOLD', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Configuration
    |--------------------------------------------------------------------------
    */
    'docs' => [
        'enabled' => (bool) env('VELO_DOCS_ENABLED', false),
        'path' => env('VELO_DOCS_PATH', 'docs'),
    ],
];
