<?php

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('allows user to switch active school context', function () {
    $school1 = School::create(['name' => 'School 1']);
    $school2 = School::create(['name' => 'School 2']);

    $user = User::create(['name' => 'User', 'email' => 'user@test.com', 'password' => bcrypt('password')]);
    $user->schools()->attach($school1->id, ['is_active' => true]);
    $user->schools()->attach($school2->id, ['is_active' => false]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/auth/switch-school', [
        'school_id' => $school2->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('active_school.id', $school2->id);
});

it('does not brick user when switching to school they do not belong to', function () {
    $owned = School::create(['name' => 'Owned School']);
    $foreign = School::create(['name' => 'Foreign School']);

    $user = User::create(['name' => 'User', 'email' => 'user@test.com', 'password' => bcrypt('password')]);
    $user->schools()->attach($owned->id, ['is_active' => true]);

    Sanctum::actingAs($user);

    // Switch ke sekolah asing → ditolak
    $this->postJson('/api/v1/auth/switch-school', ['school_id' => $foreign->id])
        ->assertStatus(422);

    // Konteks sekolah milik user TIDAK boleh hilang (account tidak bricked)
    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('active_school.id', $owned->id);
});