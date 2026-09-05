<?php

use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('crud academic years scoped to school', function () {
    $school = School::create(['name' => 'SMA A']);
    Sanctum::actingAs(makeScopedUser($school));

    $this->postJson('/api/v1/academic-years', [
        'name' => '2025/2026',
        'semester' => 'ganjil',
        'start_date' => '2025-07-14',
        'end_date' => '2025-12-20',
    ])->assertCreated()->assertJsonPath('name', '2025/2026');

    $year = AcademicYear::where('school_id', $school->id)->firstOrFail();

    $this->getJson('/api/v1/academic-years?search=2025')->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson("/api/v1/academic-years/{$year->id}")->assertOk();

    $this->putJson("/api/v1/academic-years/{$year->id}", [
        'name' => '2025/2026',
        'semester' => 'genap',
    ])->assertOk()->assertJsonPath('semester', 'genap');

    $this->deleteJson("/api/v1/academic-years/{$year->id}")->assertNoContent();
    $this->assertSoftDeleted('academic_years', ['id' => $year->id]);
});

it('rejects duplicate name+semester in same school', function () {
    $school = School::create(['name' => 'SMA A']);
    Sanctum::actingAs(makeScopedUser($school));

    $this->postJson('/api/v1/academic-years', ['name' => '2025/2026', 'semester' => 'ganjil'])->assertCreated();
    $this->postJson('/api/v1/academic-years', ['name' => '2025/2026', 'semester' => 'ganjil'])->assertStatus(422);
});

it('404 when accessing academic year from another school', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);

    $yearB = AcademicYear::create(['school_id' => $schoolB->id, 'name' => '2024/2025', 'semester' => 'ganjil']);

    Sanctum::actingAs(makeScopedUser($schoolA));

    $this->getJson("/api/v1/academic-years/{$yearB->id}")->assertNotFound();
    $this->putJson("/api/v1/academic-years/{$yearB->id}", ['name' => 'X', 'semester' => 'ganjil'])->assertNotFound();
    $this->deleteJson("/api/v1/academic-years/{$yearB->id}")->assertNotFound();
});
