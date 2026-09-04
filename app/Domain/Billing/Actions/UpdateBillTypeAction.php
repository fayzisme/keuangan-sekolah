<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\BillType;

final class UpdateBillTypeAction
{
    public function __invoke(BillType $billType, array $data): BillType
    {
        $billType->update([
            'name' => $data['name'] ?? $billType->name,
            'tipe_bayar' => $data['tipe_bayar'] ?? $billType->tipe_bayar,
            'tarif_cents' => isset($data['tarif_cents']) ? (int) $data['tarif_cents'] : $billType->tarif_cents,
            'is_active' => $data['is_active'] ?? $billType->is_active,
        ]);

        return $billType->fresh();
    }
}
