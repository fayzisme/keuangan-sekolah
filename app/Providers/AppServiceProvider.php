<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            // Normalisasi email: hilangkan spasi + lowercase. Email di DB case-insensitive,
            // jadi kunci rate-limiter harus dinormalisasi agar variasi huruf tidak bypass throttle.
            $email = strtolower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });
    }
}
