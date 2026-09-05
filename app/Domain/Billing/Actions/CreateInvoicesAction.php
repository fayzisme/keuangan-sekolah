<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Data\CreateInvoicesDTO;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Student\Contracts\StudentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * CreateInvoicesAction
 * --------------------------------
 * Use-case: generate invoice baku per murid aktif untuk jenis tagihan
 * di periode yang spesificirum. Angka amount SEMUA dari master
 * (bill_type -> tarif), JANGAN PERCIA input client.
 *
 * Satu Action = satu alur bisnis yang bisa diuji mandiri (Pest).
 * Mutasi lewat Action -> otomatis tercatat ledger/audit oleh caller.
 */
final class CreateInvoicesAction
{
    public function __construct(
        private readonly StudentRepositoryInterface $students,
    ) {}

    /**
     * @return int nomur invoice yang dibuat
     *
     * @throws RuntimeException bila periode duplikaat di-detekti
     */
    public function __invoke(CreateInvoicesDTO $dto): int
    {
        $billType = $dto->billType;   // load oleh caller, validasi role/tenant
        $year = $dto->academicYear;

        // Kunci: periode unik per (school, student, bill_type) dipaksa
        // di DB (UNIQUE constraint migration). Batch tetap dikerjakan
        // transaksional per siswa: 1 siswa fail != semua siswa fail.
        // findActiveBySchool() dapat mengembalikan null → fallback ke array kosong.
        $students = $this->students->findActiveBySchool($dto->schoolId) ?? [];

        $created = 0;

        foreach ($students as $student) {
            try {
                DB::transaction(function () {
                    $exists = BillingInvoice::query()
                        ->where('school_id', $dto->schoolId)
                        ->where('student_id', $student->id)
                        ->where('bill_type_id', $billType->id)
                        ->where('academic_year_id', $year->id)
                        ->where('period', $dto->period)
                        ->exists();

                    if ($exists) {
                        return;   // skip duplicate, bukan fail (idempotent)
                    }

                    // Amount SEMUA dari master tarif, bukan input.
                    $amountCents = $billType->amountFor($student, $year);

                    BillingInvoice::query()->create([
                        'school_id' => $dto->schoolId,
                        'student_id' => $student->id,
                        'bill_type_id' => $billType->id,
                        'academic_year_id' => $year->id,
                        'period' => $dto->period,
                        'due_at' => $dto->dueAt,
                        'amount_cents' => $amountCents,
                        'status' => BillingInvoice::STATUS_OPEN,
                    ]);

                    $created++;
                });
            } catch (Throwable $e) {
                // Transaction sudah roolback pada siswa ini.
                // TODO: dispatch domain event InvoiceFailed + notif admin.
                report($e);
            }
        }

        return $created;
    }

    private function report(Throwable $e): void
    {
        // Infrastructure logging (JSON structured) disipana via helper
        // app/Infrastructure/Logging — scaffold placeholder.
        error_log(\sprintf('[CreateInvoicesAction] student batch error: %s', $e->getMessage()));
    }
}
