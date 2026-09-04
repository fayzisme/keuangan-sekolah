<?php

namespace App\Domain\Reports\Actions;

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\School\Models\ClassRoom;
use App\Domain\Student\Models\Student;
use Illuminate\Support\Facades\DB;

final class GenerateClassReportAction
{
    public function __invoke(int $schoolId, int $classId): array
    {
        $class = ClassRoom::query()->where('school_id', $schoolId)->whereKey($classId)->firstOrFail();

        $studentIds = Student::query()
            ->where('school_id', $schoolId)
            ->where('class_id', $classId)
            ->pluck('id');

        $invoices = BillingInvoice::query()
            ->where('school_id', $schoolId)
            ->whereIn('student_id', $studentIds)
            ->get();

        $totalTagihan = $invoices->sum('amount_cents');
        $totalDibayar = 0;

        foreach ($invoices as $inv) {
            $paid = DB::table('payment_invoice')
                ->join('payments', 'payments.id', '=', 'payment_invoice.payment_id')
                ->where('payment_invoice.invoice_id', $inv->id)
                ->where('payments.status', Payment::STATUS_SETTLED)
                ->sum('payment_invoice.allocated_cents');
            $totalDibayar += $paid;
        }

        return [
            'class_id' => $classId,
            'class_name' => $class->name,
            'total_siswa' => $studentIds->count(),
            'total_tagihan_cents' => $totalTagihan,
            'total_dibayar_cents' => $totalDibayar,
            'sisa_cents' => $totalTagihan - $totalDibayar,
        ];
    }
}
