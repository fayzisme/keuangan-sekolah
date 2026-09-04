<?php

namespace App\Domain\Student\Actions;

use App\Domain\Student\Models\Student;

final class CreateStudentAction
{
    public function __invoke(int $schoolId, array $data): Student
    {
        return Student::create([
            'school_id' => $schoolId,
            'class_id' => $data['class_id'] ?? null,
            'nis' => $data['nis'],
            'name' => $data['name'],
            'gender' => $data['gender'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
