<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', function () {
    $checks = [
        'app' => 'ok',
        'database' => 'unknown',
        'cache' => 'unknown',
    ];

    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (Throwable) {
        $checks['database'] = 'error';
    }

    try {
        Cache::store()->put('healthz', 'ok', 5);
        $checks['cache'] = Cache::store()->get('healthz') === 'ok' ? 'ok' : 'error';
    } catch (Throwable) {
        $checks['cache'] = 'error';
    }

    $healthy = ! in_array('error', $checks, true);

    return response()->json([
        'status' => $healthy ? 'ok' : 'degraded',
        'checks' => $checks,
    ], $healthy ? 200 : 503);
});
