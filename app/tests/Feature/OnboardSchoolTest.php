<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('onboards a new school with admin user and login works', function () {
    config(['app.platform_key' => 'test-key-123']);

    $response = $this->withHeader('X-Platform-Key', 'test-key-123')
        ->postJson('/api/v1/platform/schools', [
            'name' => 'SMA Nusantara',
            'admin_name' => 'Kepala Sekolah',
            'admin_email' => 'kepala@nusantara.sch.id',
            'admin_password' => 'rahasia-123',
        ]);

    $response->assertCreated()
        ->assertJsonPath('school.name', 'SMA Nusantara')
        ->assertJsonPath('admin.email', 'kepala@nusantara.sch.id');

    // Admin user dibuat + terhubung ke sekolah (aktif) + role admin.
    $user = User::where('email', 'kepala@nusantara.sch.id')->firstOrFail();
    expect($user->schools)->toHaveCount(1);
    expect($user->activeSchool()->name)->toBe('SMA Nusantara');
    expect($user->hasRole('admin'))->toBeTrue();

    // Login pakai kredensial admin berhasil.
    $this->postJson('/api/v1/auth/login', [
        'email' => 'kepala@nusantara.sch.id',
        'password' => 'rahasia-123',
    ])->assertOk();
});

it('rejects onboarding without valid platform key', function () {
    config(['app.platform_key' => 'test-key-123']);

    $this->postJson('/api/v1/platform/schools', [
        'name' => 'X',
        'admin_name' => 'Y',
        'admin_email' => 'y@x.sch.id',
        'admin_password' => 'password-1',
    ])->assertStatus(401);
});

it('rejects duplicate admin email on onboarding', function () {
    config(['app.platform_key' => 'test-key-123']);

    $payload = [
        'name' => 'SMA A',
        'admin_name' => 'A',
        'admin_email' => 'dupe@a.sch.id',
        'admin_password' => 'password-1',
    ];

    $this->withHeader('X-Platform-Key', 'test-key-123')->postJson('/api/v1/platform/schools', $payload)->assertCreated();
    $this->withHeader('X-Platform-Key', 'test-key-123')->postJson('/api/v1/platform/schools', $payload)->assertStatus(422);
});

it('returns 503 when platform key not configured', function () {
    config(['app.platform_key' => null]);

    $this->withHeader('X-Platform-Key', 'anything')
        ->postJson('/api/v1/platform/schools', [
            'name' => 'X', 'admin_name' => 'Y', 'admin_email' => 'z@x.sch.id', 'admin_password' => 'password-1',
        ])->assertStatus(503);
});
