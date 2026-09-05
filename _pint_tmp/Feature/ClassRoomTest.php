<?php

use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\ClassRoom;
use App\Domain\School\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('crud classes scoped to school and academic year', function () {
    $school = School::create(['name' => 'SMA A']);
    $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026', 'semester' => 'ganjil']);

    Sanctum::actingAs(makeScopedUser($school));

    $this->postJson('/api/v1/classes', [
        'academic_year_id' => $year->id,
        'name' => 'X IPA 1',
        'level' => 10,
    ])->assertCreated()->assertJsonPath('name', 'X IPA 1');

    $class = ClassRoom::where('school_id', $school->id)->firstOrFail();

    $this->getJson('/api/v1/classes?academic_year_id='.$year->id)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/classes/{$class->id}")->assertOk()->assertJsonPath('name', 'X IPA 1');

    $this->putJson("/api/v1/classes/{$class->id}", ['academic_year_id' => $year->id, 'name' => 'X IPA 2'])
        ->assertOk()->assertJsonPath('name', 'X IPA 2');

    $this->deleteJson("/api/v1/classes/{$class->id}")->assertNoContent();
    $this->assertSoftDeleted('classes', ['id' => $class->id]);
});

it('rejects class referencing academic year from another school', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);
    $yearB = AcademicYear::create(['school_id' => $schoolB->id, 'name' => '2024/2025', 'semester' => 'ganjil']);

    Sanctum::actingAs(makeScopedUser($schoolA));

    $this->postJson('/api/v1/classes', [
        'academic_year_id' => $yearB->id,
        'name' => 'X',
    ])->assertStatus(422);
});
