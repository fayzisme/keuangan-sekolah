<?php

use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\School;
use App\Domain\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('does not leak students between two schools on same DB', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);

    // Murid eksklusif di masing-masing sekolah.
    Student::create(['school_id' => $schoolA->id, 'nis' => 'A-001', 'name' => 'Siswa A']);
    Student::create(['school_id' => $schoolB->id, 'nis' => 'B-001', 'name' => 'Siswa B']);

    // Admin sekolah A
    Sanctum::actingAs(makeScopedUser($schoolA));
    $resA = $this->getJson('/api/v1/students')->assertOk();
    expect(collect($resA->json('data'))->pluck('nis')->all())->toBe(['A-001']);

    // Admin sekolah B
    Sanctum::actingAs(makeScopedUser($schoolB));
    $resB = $this->getJson('/api/v1/students')->assertOk();
    expect(collect($resB->json('data'))->pluck('nis')->all())->toBe(['B-001']);
});

it('admin of school A cannot modify student of school B', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);

    $studentB = Student::create(['school_id' => $schoolB->id, 'nis' => 'B-001', 'name' => 'Siswa B']);
    $academicB = AcademicYear::create(['school_id' => $schoolB->id, 'name' => '2024/2025', 'semester' => 'ganjil']);

    Sanctum::actingAs(makeScopedUser($schoolA));

    // show/update/delete murid milik sekolah B -> 404 (tidak bocor, tidak bisa mutasi)
    $this->getJson("/api/v1/students/{$studentB->id}")->assertNotFound();
    $this->putJson("/api/v1/students/{$studentB->id}", ['nis' => 'X', 'name' => 'Hack'])->assertNotFound();
    $this->deleteJson("/api/v1/students/{$studentB->id}")->assertNotFound();

    // tahun ajaran sekolah B juga tak tersentuh
    $this->deleteJson("/api/v1/academic-years/{$academicB->id}")->assertNotFound();
    $this->assertNotSoftDeleted('academic_years', ['id' => $academicB->id]);
});
