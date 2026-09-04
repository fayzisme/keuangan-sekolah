<?php

namespace App\Infrastructure\Finance;

use App\Domain\Billing\Models\LedgerEntry;

final class LedgerRepository
{
    /**
     * Append entry pembukuan baru (Append-Only per ADR-0008).
     */
    public function append(
        int $schoolId,
        string $refType,
        int $refId,
        int $debitCents = 0,
        int $creditCents = 0,
        ?string $note = null,
        ?int $createdBy = null,
    ): LedgerEntry {
        return LedgerEntry::create([
            'school_id' => $schoolId,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'debit_cents' => $debitCents,
            'credit_cents' => $creditCents,
            'note' => $note,
            'created_by' => $createdBy,
        ]);
    }
}
