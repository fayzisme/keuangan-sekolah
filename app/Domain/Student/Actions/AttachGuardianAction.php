<?php

namespace App\Domain\Student\Actions;

use App\Domain\Student\Models\Student;

final class AttachGuardianAction
{
    public function __invoke(Student $student, int $guardianId, string $relation = 'wali', bool $isPrimary = false): void
    {
        $student->guardians()->attach($guardianId, [
            'relation' => $relation,
            'is_primary' => $isPrimary,
        ]);
    }
}
