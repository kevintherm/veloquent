<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Documentation Configuration
    |--------------------------------------------------------------------------
    */
    'docs' => [
        'enabled' => (bool) env('VELO_DOCS_ENABLED', true),
        'path' => env('VELO_DOCS_PATH', 'docs'),
    ],
];
