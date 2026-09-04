<?php

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('allows user to login with valid credentials', function () {
    $school = School::create(['name' => 'Test School']);
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);
    $user->schools()->attach($school->id, ['is_active' => true]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user', 'schools', 'active_school']);
});

it('rejects invalid credentials', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'wrong@example.com',
        'password' => 'wrong',
    ]);

    $response->assertStatus(422);
});
