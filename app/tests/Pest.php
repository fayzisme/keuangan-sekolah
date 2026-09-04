<?php

use App\Domain\School\Models\School;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

// Bersihkan cache permission Spatie setiap test: teams mode menyimpan team-id di cache
// per request; tanpa ini role dari test sebelumnya bisa "bocor" menyatu (state bleed),
// membuat test lulus terisolasi tapi gagal saat berjalan berurutan di CI.
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/**
 * Helper test: buat user ber-role di sekolah dgn konteks aktif.
 */
function makeScopedUser(School $school, string $roleName = 'admin'): User
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
    Role::findOrCreate($roleName, 'sanctum');

    $user = User::create([
        'name' => ucfirst($roleName).' '.$school->name,
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
    ]);
    $user->schools()->attach($school->id, ['is_active' => true]);
    $user->assignRole($roleName);

    return $user;
}
