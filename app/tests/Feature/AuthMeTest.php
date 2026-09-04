<?php

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns profile with null active school when user has no active school', function () {
    $school = School::create(['name' => 'S']);
    $user = User::create([
        'name' => 'U',
        'email' => 'user@example.com',
        'password' => bcrypt('password'),
    ]);
    // Semua pivot nonaktif (default false).
    $user->schools()->attach($school->id, ['is_active' => false]);
    $user->schools()->attach(School::create(['name' => 'S2'])->id, ['is_active' => false]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('active_school', null)
        ->assertJsonPath('roles', []);
});
