<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\BillType;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\School\Models\AcademicYear;
use App\Domain\Student\Models\Student;

final class GenerateInvoicesAction
{
	/**
	 * Generate invoice batch untuk seluruh murid aktif (atau subset student_ids)
	 * di sekolah aktif, pada jenis tagihan & periode tertentu.
	 * Tarif SELALU dari bill_types.tarif_cents (master) — JANGAN dari request (DoD).
	 *
	 * @return array{generated: int, skipped: int}
	 */
	public function __invoke(array $dto): array
	{
		$schoolId = (int) $dto['school_id'];
		$billType = BillType::query()
			->where('school_id', $schoolId)
			->whereKey($dto['bill_type_id'])
			->firstOrFail();

		$year = AcademicYear::query()
			->where('school_id', $schoolId)
			->whereKey($dto['academic_year_id'])
			->firstOrFail();

		// Ambil murid aktif di sekolah ini; optional filter by student_ids.
		// CATATAN: array kosong yang eksplisit (student_ids: []) berarti "generate untuk TIDAK ADA satupun",
		// BUKAN "generate untuk semua" — karena itu dipakai array_key_exists, bukan empty().
		$students = Student::query()
			->where('school_id', $schoolId)
			->where('is_active', true);

		if (array_key_exists('student_ids', $dto) && is_array($dto['student_ids'])) {
			$students->whereIn('id', $dto['student_ids']);
		}

		$generated = 0;
		$skipped = 0;

		foreach ($students->get() as $student) {
			// createOrFirst = atomic upsert; bila unique violation (double-invoice)
			// mengembalikan model existing → race-safe tanpa try/catch manual.
			$invoice = BillingInvoice::createOrFirst(
				// kolom unique (anti double-invoice)
				[
					'school_id' => $schoolId,
					'student_id' => $student->id,
					'bill_type_id' => $billType->id,
					'academic_year_id' => $year->id,
					'periode_bulan' => $billType->tipe_bayar === 'monthly' ? (int) $dto['periode_bulan'] : null,
					'periode_tahun' => (int) $dto['periode_tahun'],
				],
				// kolom nilai (hanya dipakai saat create)
				[
					'amount_cents' => $billType->amountFor($student, $year),
					'status' => BillingInvoice::STATUS_OPEN,
					'due_at' => $dto['due_at'] ?? null,
				],
			);

			if ($invoice->wasRecentlyCreated) {
				$generated++;
			} else {
				$skipped++;
			}
		}

		return ['generated' => $generated, 'skipped' => $skipped];
	}
}