<?php

namespace App\Domain\School\Actions;

use App\Domain\School\Models\AcademicYear;

final class UpdateAcademicYearAction
{
    public function __invoke(AcademicYear $academicYear, array $data): AcademicYear
    {
        $academicYear->update([
            'name' => $data['name'] ?? $academicYear->name,
            'semester' => $data['semester'] ?? $academicYear->semester,
            'start_date' => array_key_exists('start_date', $data) ? $data['start_date'] : $academicYear->start_date,
            'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $academicYear->end_date,
            'is_active' => $data['is_active'] ?? $academicYear->is_active,
        ]);

        return $academicYear->fresh();
    }
}
