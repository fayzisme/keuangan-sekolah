<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSchoolContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (is_null($user)) {
            abort(401);
        }

        $school = $user->schools()->wherePivot('is_active', true)->first();

        if (is_null($school)) {
            return response()->json(['message' => 'No active school context.'], 403);
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

        $request->attributes->set('school_id', $school->id);
        $request->attributes->set('school', $school);

        return $next($request);
    }
}
