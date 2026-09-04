<?php

namespace App\Domain\School\Actions;

use App\Domain\School\Models\AcademicYear;

final class CreateAcademicYearAction
{
    public function __invoke(int $schoolId, array $data): AcademicYear
    {
        return AcademicYear::create([
            'school_id' => $schoolId,
            'name' => $data['name'],
            'semester' => $data['semester'] ?? 'ganjil',
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
