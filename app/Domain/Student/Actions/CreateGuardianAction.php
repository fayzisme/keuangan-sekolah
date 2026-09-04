<?php

namespace App\Domain\Student\Actions;

use App\Domain\Student\Models\Guardian;

final class CreateGuardianAction
{
    public function __invoke(array $data): Guardian
    {
        return Guardian::create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);
    }
}
