<?php

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('revokes current token after logout', function () {
    $school = School::create(['name' => 'S']);
    $user = User::create([
        'name' => 'U',
        'email' => 'user@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->schools()->attach($school->id, ['is_active' => true]);

    // Token nyata (bukan actingAs agar bisa dicek revocation per-token).
    $token = $user->createToken('auth_token')->plainTextToken;

    // Logout dengan token tsb.
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent(); // 204

    // Token yang sama tidak valid lagi.
    // NOTE: app di-refresh agar guard sanctum tidak me-cache user dari request
    // sebelumnya (guard singleton per-instance app). Di produksi setiap request
    // adalah proses terpisah, jadi ini murni artefak testing.
    $this->refreshApplication();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);
});
