<?php

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('returns 429 when login attempted more than 5 times per minute', function () {
    School::create(['name' => 'S']);
    User::create([
        'name' => 'U',
        'email' => 'user@example.com',
        'password' => Hash::make('password'),
    ]);

    // 5 percobaan gagal: masih diperbolehkan (422).
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong'.$i,
        ])->assertStatus(422);
    }

    // Percobaan ke-6: throttle -> 429 Too Many Requests.
    $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-6',
    ])->assertStatus(429);
});

it('treats email case-variation as same rate-limit bucket', function () {
    School::create(['name' => 'S']);
    User::create([
        'name' => 'U',
        'email' => 'user@example.com',
        'password' => Hash::make('password'),
    ]);

    // Variasi huruf kapital tetap satu bucket -> habis setelah total 5.
    for ($i = 0; $i < 5; $i++) {
        $emails = ['user@example.com', 'User@example.com', 'USER@example.com', 'UsEr@example.com'];
        $this->postJson('/api/v1/auth/login', [
            'email' => $emails[$i % 4],
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-final',
    ])->assertStatus(429);
});
