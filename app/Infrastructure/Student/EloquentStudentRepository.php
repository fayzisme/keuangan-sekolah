<?php

namespace App\Infrastructure\Student;

use App\Domain\Student\Contracts\StudentRepositoryInterface;
use App\Domain\Student\Models\Student;
use RuntimeException;

/**
 * EloquentStudentRepository — ADAPTER (infrastructure)
 * Implementasi interface Domain wo Eloquent. Lapisan ini boleh tahu DB;
 * lapisan Domain tidak.
 */
final class EloquentStudentRepository implements StudentRepositoryInterface
{
    public function findActiveBySchool(int $schoolId): array
    {
        // Global scope tenant (BelongsToSchool trait) auto-diberlakukan.
        return Student::query()->where('school_id', $schoolId)->where('is_active', true)->get()->all();
    }

    public function findOwnedBySchool(int $studentId, int $schoolId): Student
    {
        $student = Student::query()->whereKey($studentId)->where('school_id', $schoolId)->first();

        if (is_null($student)) {
            throw new RuntimeException('Murid tidak ditemukan atau bukan di sekolah konteks.');
        }

        return $student;
    }

    public function guardiansOf(Student $student): array
    {
        return $student->guardians()->get()->all();
    }
}
