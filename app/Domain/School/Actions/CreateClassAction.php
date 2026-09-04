<?php

namespace App\Domain\School\Actions;

use App\Domain\School\Models\ClassRoom;

final class CreateClassAction
{
    public function __invoke(int $schoolId, array $data): ClassRoom
    {
        return ClassRoom::create([
            'school_id' => $schoolId,
            'academic_year_id' => $data['academic_year_id'],
            'name' => $data['name'],
            'level' => $data['level'] ?? null,
        ]);
    }
}
