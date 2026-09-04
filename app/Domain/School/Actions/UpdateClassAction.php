<?php

namespace App\Domain\School\Actions;

use App\Domain\School\Models\ClassRoom;

final class UpdateClassAction
{
    public function __invoke(ClassRoom $class, array $data): ClassRoom
    {
        $class->update([
            'academic_year_id' => $data['academic_year_id'] ?? $class->academic_year_id,
            'name' => $data['name'] ?? $class->name,
            'level' => array_key_exists('level', $data) ? $data['level'] : $class->level,
        ]);

        return $class->fresh();
    }
}
