<?php

use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\School;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function masterFix(School $school)
{
    return makeScopedUser($school);
}

it('allows reusing nis after student soft-deleted', function () {
    $school = School::create(['name' => 'SMA A']);
    Sanctum::actingAs(masterFix($school));

    $this->postJson('/api/v1/students', ['nis' => '2025009', 'name' => 'Alumni'])->assertCreated();

    $alumni = Student::where('school_id', $school->id)->where('nis', '2025009')->firstOrFail();
    $this->deleteJson("/api/v1/students/{$alumni->id}")->assertNoContent();

    // Re-create NIS yang sama setelah soft delete → HARUS 201, bukan 500 constraint.
    $this->postJson('/api/v1/students', ['nis' => '2025009', 'name' => 'Siswa Baru'])->assertCreated();
});

it('allows reusing academic year name after soft-deleted', function () {
    $school = School::create(['name' => 'SMA A']);
    Sanctum::actingAs(masterFix($school));

    $this->postJson('/api/v1/academic-years', ['name' => '2025/2026', 'semester' => 'ganjil'])->assertCreated();

    $year = AcademicYear::where('school_id', $school->id)->firstOrFail();
    $this->deleteJson("/api/v1/academic-years/{$year->id}")->assertNoContent();

    $this->postJson('/api/v1/academic-years', ['name' => '2025/2026', 'semester' => 'ganjil'])->assertCreated();
});

it('rejects attaching guardian that belongs to another school', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);

    $studentA = Student::create(['school_id' => $schoolA->id, 'nis' => 'A-1', 'name' => 'Siswa A']);
    $studentB = Student::create(['school_id' => $schoolB->id, 'nis' => 'B-1', 'name' => 'Siswa B']);
    $guardianB = Guardian::create(['name' => 'Ortu Siswa B', 'phone' => '081234567890']);
    $studentB->guardians()->attach($guardianB->id, ['relation' => 'ayah', 'is_primary' => true]);

    Sanctum::actingAs(masterFix($schoolA));

    // Admin A mencoba menautkan guardian milik sekolah B ke murid A → 422 (ditolak).
    $this->postJson("/api/v1/students/{$studentA->id}/guardians", [
        'guardian_id' => $guardianB->id,
        'relation' => 'wali',
    ])->assertStatus(422);

    expect($studentA->guardians()->count())->toBe(0);
});

it('allows attaching orphan guardian (no students yet)', function () {
    $school = School::create(['name' => 'SMA A']);
    $student = Student::create(['school_id' => $school->id, 'nis' => 'A-2', 'name' => 'Siswa A']);
    $orphan = Guardian::create(['name' => 'Ortu Baru', 'phone' => '08123']);

    Sanctum::actingAs(masterFix($school));

    $this->postJson("/api/v1/students/{$student->id}/guardians", [
        'guardian_id' => $orphan->id,
    ])->assertOk();

    expect($student->guardians()->count())->toBe(1);
});

it('allows guardian already linked to another student in same school', function () {
    $school = School::create(['name' => 'SMA A']);
    $studentA = Student::create(['school_id' => $school->id, 'nis' => 'A-3', 'name' => 'Siswa A']);
    $studentB = Student::create(['school_id' => $school->id, 'nis' => 'A-4', 'name' => 'Siswa B']);
    $guardian = Guardian::create(['name' => 'Ortu Bersama', 'phone' => '08123']);
    $studentA->guardians()->attach($guardian->id, ['relation' => 'ibu', 'is_primary' => true]);

    Sanctum::actingAs(masterFix($school));

    // Ortu dengan 2 anak di sekolah yang sama → valid.
    $this->postJson("/api/v1/students/{$studentB->id}/guardians", [
        'guardian_id' => $guardian->id,
    ])->assertOk();

    expect($studentB->guardians()->count())->toBe(1);
});