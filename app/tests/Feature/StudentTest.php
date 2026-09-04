<?php

use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\ClassRoom;
use App\Domain\School\Models\School;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function seedClass(School $school): ClassRoom
{
    $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026', 'semester' => 'ganjil']);

    return ClassRoom::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'X IPA 1']);
}

it('crud students scoped to school', function () {
    $school = School::create(['name' => 'SMA A']);
    $class = seedClass($school);

    Sanctum::actingAs(makeScopedUser($school));

    $this->postJson('/api/v1/students', [
        'class_id' => $class->id,
        'nis' => '2025001',
        'name' => 'Budi Santoso',
        'gender' => 'L',
        'birth_date' => '2010-05-01',
    ])->assertCreated()->assertJsonPath('nis', '2025001');

    $student = Student::where('school_id', $school->id)->firstOrFail();

    $this->getJson('/api/v1/students?search=budi')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/students/{$student->id}")->assertOk()->assertJsonPath('name', 'Budi Santoso');

    $this->putJson("/api/v1/students/{$student->id}", [
        'class_id' => $class->id,
        'nis' => '2025001',
        'name' => 'Budi Santoso Putra',
    ])->assertOk()->assertJsonPath('name', 'Budi Santoso Putra');

    $this->deleteJson("/api/v1/students/{$student->id}")->assertNoContent();
    $this->assertSoftDeleted('students', ['id' => $student->id]);
});

it('rejects duplicate nis within same school', function () {
    $school = School::create(['name' => 'SMA A']);
    $class = seedClass($school);

    Sanctum::actingAs(makeScopedUser($school));

    $this->postJson('/api/v1/students', ['class_id' => $class->id, 'nis' => '2025001', 'name' => 'A'])->assertCreated();
    $this->postJson('/api/v1/students', ['class_id' => $class->id, 'nis' => '2025001', 'name' => 'B'])->assertStatus(422);
});

it('attaches and detaches guardian to student idempotently', function () {
    $school = School::create(['name' => 'SMA A']);
    $class = seedClass($school);
    $student = Student::create(['school_id' => $school->id, 'class_id' => $class->id, 'nis' => '2025002', 'name' => 'Ani']);
    $guardian = Guardian::create(['name' => 'Ibu Ani', 'phone' => '08123456789']);

    Sanctum::actingAs(makeScopedUser($school));

    // attach
    $this->postJson("/api/v1/students/{$student->id}/guardians", [
        'guardian_id' => $guardian->id,
        'relation' => 'ibu',
        'is_primary' => true,
    ])->assertOk();

    expect($student->guardians()->count())->toBe(1);

    // attach ulang = idempoten metadata update, bukan duplikat baris pivot
    $this->postJson("/api/v1/students/{$student->id}/guardians", [
        'guardian_id' => $guardian->id,
        'relation' => 'ibu',
    ])->assertOk();

    expect($student->guardians()->count())->toBe(1);
    expect($student->guardians()->where('guardians.id', $guardian->id)->first()->pivot->is_primary)->toBeTrue();

    // detach
    $this->deleteJson("/api/v1/students/{$student->id}/guardians/{$guardian->id}")->assertNoContent();
    expect($student->guardians()->count())->toBe(0);
});

it('guardian index and show are scoped via students of the school', function () {
    $school = School::create(['name' => 'SMA A']);
    $class = seedClass($school);
    $student = Student::create(['school_id' => $school->id, 'class_id' => $class->id, 'nis' => '2025003', 'name' => 'Caca']);
    $guardian = Guardian::create(['name' => 'Bpk Caca', 'phone' => '08111']);
    $student->guardians()->attach($guardian->id, ['relation' => 'ayah', 'is_primary' => true]);

    Sanctum::actingAs(makeScopedUser($school));

    $this->getJson('/api/v1/guardians')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/guardians/{$guardian->id}")->assertOk()->assertJsonPath('name', 'Bpk Caca');

    // guardian tanpa murid di sekolah ini tidak muncul
    $orphan = Guardian::create(['name' => 'Tanpa Murid']);
    $this->getJson('/api/v1/guardians')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/guardians/{$orphan->id}")->assertNotFound();
});
