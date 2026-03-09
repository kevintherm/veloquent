<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT Secret
    |--------------------------------------------------------------------------
    |
    | The secret key used for signing JWTs. This should be a long, random string.
    | Generate one with: php artisan jwt:secret (or use any random string generator).
    |
    */

    'secret' => env('JWT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | JWT Algorithm
    |--------------------------------------------------------------------------
    |
    | The hashing algorithm used to sign the JWT (e.g. HS256, HS384, HS512).
    |
    */

    'algorithm' => env('JWT_ALGO', 'HS256'),

    /*
    |--------------------------------------------------------------------------
    | JWT TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | The number of minutes a token is valid for. After this time the token
    | will be considered expired and the user will need to re-authenticate.
    |
    */

    'ttl' => (int) env('JWT_TTL', 60),

];
