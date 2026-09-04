<?php

return [
    'api_path' => 'api/v1',
    'api_domain' => null,
    'info' => [
        'version' => env('APP_VERSION', '0.1.0'),
        'description' => 'OpenAPI contract untuk School Finance API.',
    ],
    'servers' => [
        'Local' => env('APP_URL', 'http://localhost'),
    ],
];
