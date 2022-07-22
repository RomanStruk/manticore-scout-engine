<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ManticoreSearch Configuration
    |--------------------------------------------------------------------------
    |
    | See: https://manual.manticoresearch.com/Introduction
    |
    */

    'manticore' => [
        'host' => env('MANTICORE_HOST', 'localhost'),
        'port' => env('MANTICORE_PORT', '9308'),
    ],
];
