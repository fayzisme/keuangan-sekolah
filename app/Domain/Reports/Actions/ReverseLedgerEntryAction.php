<?php

namespace App\Domain\Reports\Actions;

use App\Domain\Billing\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ReverseLedgerEntryAction
{
    public function __invoke(int $ledgerEntryId, int $reversedByUserId, string $reason): LedgerEntry
    {
        $original = LedgerEntry::findOrFail($ledgerEntryId);

        if ($original->ref_type !== 'payment') {
            throw new RuntimeException('Hanya entry payment yang bisa di-reverse.');
        }

        $reversal = DB::transaction(function () use ($original, $reversedByUserId, $reason) {
            // Cek apakah sudah di-reverse
            $exists = LedgerEntry::query()
                ->where('ref_type', 'reversal')
                ->whereRaw("JSON_EXTRACT(note, '$.reverses_id') = ?", [$original->id])
                ->exists();

            if ($exists) {
                throw new RuntimeException('Entry ini sudah di-reverse.');
            }

            return LedgerEntry::create([
                'school_id' => $original->school_id,
                'ref_type' => 'reversal',
                'ref_id' => $original->ref_id,
                'debit_cents' => $original->credit_cents,
                'credit_cents' => $original->debit_cents,
                'note' => json_encode(['reason' => $reason, 'reverses_id' => $original->id, 'reversed_by' => $reversedByUserId]),
                'created_by' => $reversedByUserId,
            ]);
        });

        return $reversal->fresh();
    }
}
