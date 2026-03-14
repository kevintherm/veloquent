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
];
