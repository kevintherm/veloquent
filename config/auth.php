<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This application uses custom JWT authentication (not Laravel's built-in
    | auth guards). This config is kept minimal for any packages that may
    | reference it.
    |
    */

    'defaults' => [
        'guard' => 'api',
    ],

    'guards' => [
        'api' => [
            'driver' => 'jwt',
        ],
    ],

    'refresh_token_ttl' => 30, // in days

];
