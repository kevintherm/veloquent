<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Opaque Token TTL (minutes)
    |--------------------------------------------------------------------------
    |
    | The lifetime for issued bearer tokens in minutes.
    |
    */

    'ttl' => (int) env('TOKEN_AUTH_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Max Active Tokens Per Record
    |--------------------------------------------------------------------------
    |
    | Set to 0 to disable limiting. When greater than zero, older active
    | tokens are revoked as new tokens are issued.
    |
    */

    'max_active_tokens' => (int) env('TOKEN_AUTH_MAX_ACTIVE', 0),

];
