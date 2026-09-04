<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\BillType;

final class CreateBillTypeAction
{
    public function __invoke(int $schoolId, array $data): BillType
    {
        return BillType::create([
            'school_id' => $schoolId,
            'name' => $data['name'],
            'tipe_bayar' => $data['tipe_bayar'] ?? 'monthly',
            'tarif_cents' => (int) $data['tarif_cents'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
