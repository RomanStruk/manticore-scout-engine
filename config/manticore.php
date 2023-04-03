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

    'mysql-connection' => [
        'host' => env('MANTICORE_MYSQL_HOST', '127.0.0.1'),
        'port' => env('MANTICORE_MYSQL_PORT', '9306'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Manticore default engine
    |--------------------------------------------------------------------------
    |
    | http-client - \Manticoresearch\Client
    | mysql-builder - \RomanStruk\ManticoreScoutEngine\Builder
    |
    */
    'engine' => env('MANTICORE_ENGINE', 'mysql-builder'),

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

    'paginate_max_matches' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Escaping characters in query string
    |--------------------------------------------------------------------------
    |
    | As some characters are used as operators in the query string, they should be escaped to avoid query errors or unwanted matching conditions.
    |
    | See: https://manual.manticoresearch.com/Searching/Full_text_matching/Escaping#Escaping-characters-in-query-string
    |
    | Set false for disable auto escaping
    |
    */

    'auto_escape_search_phrase' => true,
];
