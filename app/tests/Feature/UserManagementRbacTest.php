<?php

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('admin creates a school user with roles scoped to the school', function () {
    $school = School::create(['name' => 'SMA A']);
    $admin = makeScopedUser($school, 'admin');
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/auth/users', [
        'name' => 'Khalid Verifier',
        'email' => ' Khalid@Verifier.test ',
        'password' => 'Super-Rahasia!',
        'roles' => ['bendahara'],
    ])->assertCreated()
        ->assertJsonPath('name', 'Khalid Verifier')
        // email dinormalkan (lowercase + trim)
        ->assertJsonPath('email', 'khalid@verifier.test')
        ->assertJsonPath('roles.0', 'bendahara');

    $this->assertDatabaseHas('users', ['email' => 'khalid@verifier.test']);
    $this->assertDatabaseHas('school_user', [
        'user_id' => User::where('email', 'khalid@verifier.test')->first()->id,
        'school_id' => $school->id,
    ]);
});

it('admin updates name, roles and password of a school user', function () {
    $school = School::create(['name' => 'SMA A']);
    $admin = makeScopedUser($school, 'admin');
    $user = makeScopedUser($school, 'bendahara');
    Sanctum::actingAs($admin);

    $this->putJson("/api/v1/auth/users/{$user->id}", [
        'name' => 'Bendahara Baru',
        'roles' => ['murid', 'ortua'],
    ])->assertOk()
        ->assertJsonPath('name', 'Bendahara Baru');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Bendahara Baru']);
    $this->assertDatabaseHas('model_has_roles', [
        'model_id' => $user->id,
        'role_id' => Role::where('name', 'murid')->where('school_id', $school->id)->first()->id,
    ]);
});

it('admin cannot delete their own account', function () {
    $school = School::create(['name' => 'SMA A']);
    $admin = makeScopedUser($school, 'admin');
    Sanctum::actingAs($admin);

    $this->deleteJson("/api/v1/auth/users/{$admin->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Admin kan zijn eigen account niet verwijderen.');

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

it('admin deletes another user and removes the school pivot', function () {
    $school = School::create(['name' => 'SMA A']);
    $admin = makeScopedUser($school, 'admin');
    $user = makeScopedUser($school, 'bendahara');
    Sanctum::actingAs($admin);

    $this->deleteJson("/api/v1/auth/users/{$user->id}")->assertNoContent();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('school_user', ['user_id' => $user->id, 'school_id' => $school->id]);
});

it('deleting a user in multiple schools only detaches the current school', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);
    $adminA = makeScopedUser($schoolA, 'admin');
    $user = makeScopedUser($schoolA, 'bendahara');
    $user->schools()->attach($schoolB->id, ['is_active' => false]);
    Sanctum::actingAs($adminA);

    $this->deleteJson("/api/v1/auth/users/{$user->id}")->assertNoContent();

    // user blijft bestaan — nog verbonden aan school B
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('school_user', ['user_id' => $user->id, 'school_id' => $schoolA->id]);
    $this->assertDatabaseHas('school_user', ['user_id' => $user->id, 'school_id' => $schoolB->id]);
});

it('bendahara cannot create, update or delete users', function () {
    $school = School::create(['name' => 'SMA A']);
    $bendahara = makeScopedUser($school, 'bendahara');
    $user = makeScopedUser($school, 'murid');
    Sanctum::actingAs($bendahara);

    $this->postJson('/api/v1/auth/users', [
        'name' => 'X',
        'email' => 'x@guru.test',
        'password' => '12345678',
        'roles' => ['murid'],
    ])->assertStatus(403);

    $this->putJson("/api/v1/auth/users/{$user->id}", ['name' => 'Z'])->assertStatus(403);
    $this->deleteJson("/api/v1/auth/users/{$user->id}")->assertStatus(403);
});

it('validates user input: duplicate email, unknown role, short password', function () {
    $school = School::create(['name' => 'SMA A']);
    $admin = makeScopedUser($school, 'admin');
    $existing = makeScopedUser($school, 'murid');
    Sanctum::actingAs($admin);

    $payload = [
        'name' => 'Budi',
        'email' => 'budi@test.test',
        'password' => 'rahasia123',
        'roles' => ['murid'],
    ];

    // duplicate email
    $this->postJson('/api/v1/auth/users', array_merge($payload, ['email' => $existing->email]))
        ->assertStatus(422);

    // unknown role
    $this->postJson('/api/v1/auth/users', array_merge($payload, ['roles' => ['superadmin']]))
        ->assertStatus(422);

    // short password
    $this->postJson('/api/v1/auth/users', array_merge($payload, ['password' => 'abc']))
        ->assertStatus(422);
});

it('admin cannot manage a user from another school (tenant isolation)', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);
    $adminA = makeScopedUser($schoolA, 'admin');
    $userB = makeScopedUser($schoolB, 'bendahara');
    Sanctum::actingAs($adminA);

    $this->putJson("/api/v1/auth/users/{$userB->id}", ['name' => 'Dihack'])->assertNotFound();
    $this->deleteJson("/api/v1/auth/users/{$userB->id}")->assertNotFound();
});
