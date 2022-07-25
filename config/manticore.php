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

    'connection' => [
        'host' => env('MANTICORE_HOST', 'localhost'),
        'port' => env('MANTICORE_PORT', '9308'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ManticoreSearch max_matches Configuration
    |--------------------------------------------------------------------------
    |
    | Maximum amount of matches that the server keeps in RAM for each index and can return to the client. Default is 1000.
    |
    | See: https://manual.manticoresearch.com/Searching/Options#max_matches
    |
    | Set null for calculate offset + limit
    |
    */
    'max_matches' => 1000,
];
