<?php

namespace App\Infrastructure\Finance;

use App\Domain\Billing\Models\ReceiptSequence;
use Illuminate\Support\Facades\DB;

final class ReceiptSequenceRepository
{
    /**
     * Ambil nomor kuitansi berikutnya yang terkunci per sekolah & tahun ajaran (Pessimistic Lock).
     */
    public function nextForSchoolAndYear(int $schoolId, int $academicYearId): string
    {
        return DB::transaction(function () use ($schoolId, $academicYearId) {
            $seq = ReceiptSequence::query()
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                $seq = ReceiptSequence::create([
                    'school_id' => $schoolId,
                    'academic_year_id' => $academicYearId,
                    'last_number' => 0,
                ]);
            }

            $next = $seq->last_number + 1;
            $seq->update(['last_number' => $next]);

            return sprintf('KW/%d/%d/%05d', $schoolId, $academicYearId, $next);
        });
    }
}
