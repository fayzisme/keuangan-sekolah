<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Beheer van schoolgebruikers (admin-only).
 * Rollen worden gescoped naar de actieve school (team_foreign_key = school_id).
 */
final class UserController extends Controller
{
    private function scoped(Request $request): Builder
    {
        $schoolId = (int) $request->attributes->get('school_id');

        return User::query()->whereHas('schools', fn ($q) => $q->where('schools.id', $schoolId));
    }

    private function present(User $user, int $schoolId): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
        ];
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $schoolId = (int) $request->attributes->get('school_id');
        $data = $request->validated();

        $user = DB::transaction(function () use ($data, $schoolId) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // Koppel aan actieve school; eerste school wordt de actieve school.
            $user->schools()->attach($schoolId, ['is_active' => true]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
            foreach ($data['roles'] as $role) {
                Role::findOrCreate($role, 'sanctum');
            }
            $user->syncRoles($data['roles']);

            return $user;
        });

        return response()->json($this->present($user, $schoolId), 201);
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $schoolId = (int) $request->attributes->get('school_id');
        $user = $this->scoped($request)->findOrFail($id);
        $data = $request->validated();

        DB::transaction(function () use ($user, $data, $schoolId) {
            $update = [];
            if (array_key_exists('name', $data)) {
                $update['name'] = $data['name'];
            }
            if (array_key_exists('email', $data)) {
                $update['email'] = $data['email'];
            }
            if (array_key_exists('password', $data)) {
                $update['password'] = Hash::make($data['password']);
            }
            if (! empty($update)) {
                $user->update($update);
            }

            if (array_key_exists('roles', $data)) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
                foreach ($data['roles'] as $role) {
                    Role::findOrCreate($role, 'sanctum');
                }
                $user->syncRoles($data['roles']);
            }
        });

        return response()->json($this->present($user->fresh(), $schoolId));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $schoolId = (int) $request->attributes->get('school_id');
        $user = $this->scoped($request)->findOrFail($id);

        // Regel: admin mag zijn eigen account NIET verwijderen.
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Admin kan zijn eigen account niet verwijderen.'], 409);
        }

        DB::transaction(function () use ($user, $schoolId) {
            $user->schools()->detach($schoolId);

            // Als de gebruiker aan geen enkele school meer is gekoppeld → verwijder de rij.
            if ($user->schools()->count() === 0) {
                $user->delete();
            }
        });

        return response()->json(null, 204);
    }
}
