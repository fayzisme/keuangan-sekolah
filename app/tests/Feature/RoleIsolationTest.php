<?php

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('forbids murid from admin-only users endpoint', function () {
    $school = School::create(['name' => 'Test School']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

    Role::findOrCreate('admin', 'sanctum');
    Role::findOrCreate('murid', 'sanctum');

    $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);
    $admin->schools()->attach($school->id, ['is_active' => true]);
    $admin->assignRole('admin');

    $murid = User::create(['name' => 'Murid', 'email' => 'murid@test.com', 'password' => bcrypt('password')]);
    $murid->schools()->attach($school->id, ['is_active' => true]);
    $murid->assignRole('murid');

    Sanctum::actingAs($admin);
    $this->getJson('/api/v1/auth/users')->assertOk();

    Sanctum::actingAs($murid);
    $this->getJson('/api/v1/auth/users')->assertStatus(403);
});

it('isolates role per school (admin di S1 tidak berlaku di S2)', function () {
    $schoolA = School::create(['name' => 'School A']);
    $schoolB = School::create(['name' => 'School B']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolA->id);
    Role::findOrCreate('admin', 'sanctum');
    Role::findOrCreate('murid', 'sanctum');

    $user = User::create(['name' => 'Dual', 'email' => 'dual@test.com', 'password' => bcrypt('password')]);
    $user->schools()->attach($schoolA->id, ['is_active' => true]);
    $user->schools()->attach($schoolB->id, ['is_active' => false]);

    // Admin di School A
    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolA->id);
    $user->assignRole('admin');

    // Murid di School B — teams mode: role harus dibuat ulang di konteks team B
    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolB->id);
    Role::findOrCreate('murid', 'sanctum');
    $user->assignRole('murid');

    // Konteks aktif School A -> admin, akses diterima
    app(PermissionRegistrar::class)->setPermissionsTeamId($schoolA->id);
    $user->schools()->updateExistingPivot($schoolA->id, ['is_active' => true]);
    $user->schools()->updateExistingPivot($schoolB->id, ['is_active' => false]);

    Sanctum::actingAs($user);
    $this->getJson('/api/v1/auth/users')->assertOk();

    // Konteks aktif School B -> hanya murid, akses ditolak 403
    $user->schools()->updateExistingPivot($schoolA->id, ['is_active' => false]);
    $user->schools()->updateExistingPivot($schoolB->id, ['is_active' => true]);

    // Relasi roles & sekolah sudah ter-load pada request pertama (konteks A).
    // Bersihkan agar request berikutnya membaca ulang dari DB sesuai school B
    // (di produksi tiap request = app fresh, jadi ini artefak harness test).
    $user->unsetRelation('roles');
    $user->unsetRelation('schools');

    $this->getJson('/api/v1/auth/users')->assertStatus(403);
});
