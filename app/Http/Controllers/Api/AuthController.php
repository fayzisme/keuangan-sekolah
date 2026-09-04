<?php

namespace App\Http\Controllers\Api;

use App\Domain\Auth\Actions\LoginAction;
use App\Domain\Auth\Actions\SwitchSchoolAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

final class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action($request);

        return response()->json($result);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        // Sesuai ADR-0005: konteks sekolah dari sesi user (pivot is_active), bukan header/request.
        $activeSchool = $user->activeSchool();

        if (! is_null($activeSchool)) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($activeSchool->id);
            $roles = $user->getRoleNames();
        } else {
            // User tanpa sekolah aktif tetap bisa lihat profil; role tidak dievaluasi tanpa team.
            $roles = [];
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'roles' => $roles,
            'active_school' => $activeSchool ? ['id' => $activeSchool->id, 'name' => $activeSchool->name] : null,
            'schools' => $user->schools->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'is_active' => (bool) $s->pivot->is_active]),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Cabut token saat ini (revocation) — token lain milik user tetap berlaku.
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function switchSchool(Request $request, SwitchSchoolAction $action): JsonResponse
    {
        $request->validate([
            // exists: menolak id non-eksisten / negatif sebelum menyentuh DB mutasi.
            'school_id' => ['required', 'integer', 'exists:schools,id'],
        ]);

        $result = $action($request->user(), (int) $request->school_id);

        return response()->json($result);
    }

    public function users(Request $request): JsonResponse
    {
        // Dilindungi school.context: school_id sudah dipaksa dari sesi (bukan request).
        $schoolId = $request->attributes->get('school_id');
        app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);

        $users = User::query()
            ->whereHas('schools', fn ($q) => $q->where('schools.id', $schoolId))
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => $u->getRoleNames(),
                ];
            });

        return response()->json(['data' => $users]);
    }
}
