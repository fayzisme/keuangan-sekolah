<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate endpoint level-platform (mis. onboarding sekolah).
 * Membaca header X-Platform-Key dan membandingkan dengan config('app.platform_key')
 * (env PLATFORM_KEY). Jika key tidak dikonfigurasi, endpoint tidak tersedia (503).
 */
final class EnsurePlatformKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('app.platform_key');

        if (empty($expected)) {
            return response()->json(['message' => 'Platform key belum dikonfigurasi.'], 503);
        }

        $provided = $request->header('X-Platform-Key');

        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
