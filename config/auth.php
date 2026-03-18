<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This application uses custom opaque bearer token authentication.
    | This config is kept minimal for any packages that may reference it.
    |
    */

    'defaults' => [
        'guard' => 'api',
    ],

    'guards' => [
        'api' => [
            'driver' => 'opaque_token',
        ],
    ],

];
