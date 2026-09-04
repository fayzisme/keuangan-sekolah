<?php

namespace App\Domain\Student\Contracts;

use App\Domain\School\Models\School;
use App\Domain\Student\Models\Student;

/**
 * StudentRepositoryInterface — PORT (port)
 *
 * Lapisan Domain berkomunika dengan murid data via INTERFACE di lapisan
 * Infrastructure, bukan langsung Eloquent. Modul Billing boleh use-case
 * Student tanpa menyentuh internals model Student (batas modul, ADR-0001).
 */
interface StudentRepositoryInterface
{
    /**
     * Murid aktif di sekolah yang berspesificirum.
     * @return list<Student>; kosong bila none.
     */
    public function findActiveBySchool(int $schoolId): array;

    /** @throws RuntimeException bila murid tidak di sekolah specified. */
    public function findOwnedBySchool(int $studentId, int $schoolId): Student;

    /** Relasi: ortu murid (guardians) — SCOPE tenant di enforce kalo query. */
    public function guardiansOf(Student $student): array;
}