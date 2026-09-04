<?php

namespace App\Domain\Student\Actions;

use App\Domain\Student\Models\Student;

final class UpdateStudentAction
{
    public function __invoke(Student $student, array $data): Student
    {
        $student->update([
            'class_id' => array_key_exists('class_id', $data) ? $data['class_id'] : $student->class_id,
            'nis' => $data['nis'] ?? $student->nis,
            'name' => $data['name'] ?? $student->name,
            'gender' => array_key_exists('gender', $data) ? $data['gender'] : $student->gender,
            'birth_date' => array_key_exists('birth_date', $data) ? $data['birth_date'] : $student->birth_date,
            'is_active' => $data['is_active'] ?? $student->is_active,
        ]);

        return $student->fresh();
    }
}
