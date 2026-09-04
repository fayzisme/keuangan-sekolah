<?php

return [
    'name' => env('APP_NAME', 'School Finance'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
    'locale' => env('APP_LOCALE', 'id'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'id_ID'),
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
    // Kunci level-platform untuk endpoint onboarding sekolah.
    // Wajib diisi DI LUAR produksi? Tidak — WAJIB diisi di production, kosong=dimatikan (503).
    'platform_key' => env('PLATFORM_KEY'),
    // Laravel 12 mendaftarkan provider via bootstrap/providers.php (dipakai kernel).
    'providers' => [],
];
