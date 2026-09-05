<?php

use App\Domain\School\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('bendahara can read but not write master data', function () {
    $school = School::create(['name' => 'SMA A']);
    $bendahara = makeScopedUser($school, 'bendahara');

    Sanctum::actingAs($bendahara);

    // read OK
    $this->getJson('/api/v1/academic-years')->assertOk();
    $this->getJson('/api/v1/classes')->assertOk();
    $this->getJson('/api/v1/students')->assertOk();
    $this->getJson('/api/v1/guardians')->assertOk();

    // write 403
    $this->postJson('/api/v1/academic-years', ['name' => '2025/2026', 'semester' => 'ganjil'])->assertStatus(403);
    $this->postJson('/api/v1/students', ['nis' => '1', 'name' => 'X'])->assertStatus(403);
});

it('murid cannot read master data at all', function () {
    $school = School::create(['name' => 'SMA A']);
    $murid = makeScopedUser($school, 'murid');

    Sanctum::actingAs($murid);

    $this->getJson('/api/v1/academic-years')->assertStatus(403);
    $this->getJson('/api/v1/students')->assertStatus(403);
});
