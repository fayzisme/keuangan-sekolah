<?php

return [
    'default' => env('CACHE_STORE', 'database'),
    'stores' => [
        'array' => ['driver' => 'array'],
        'database' => ['driver' => 'database', 'table' => 'cache', 'connection' => null, 'lock_connection' => null],
        'redis' => ['driver' => 'redis', 'connection' => 'cache', 'lock_connection' => 'default'],
    ],
    'prefix' => env('CACHE_PREFIX', 'school_finance_cache'),
];
