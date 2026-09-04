<?php

use App\Http\Middleware\EnsurePlatformKey;
use App\Http\Middleware\EnsureSchoolContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: null,
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // Alias middleware tenant context. Dipakai pada grup route yang membutuhkan sekolah aktif.
            'school.context' => EnsureSchoolContext::class,
            // Alias middleware RBAC spatie/laravel-permission.
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'platform.key' => EnsurePlatformKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Semua error response dalam bentuk JSON karena backend adalah API murni.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })
    ->create();
