<?php

namespace App\Domain\Reports\Actions;

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\Payment;
use Illuminate\Support\Facades\DB;

final class GenerateStudentReportAction
{
    public function __invoke(int $schoolId, int $studentId): array
    {
        $invoices = BillingInvoice::query()
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->with(['billType:id,name,tipe_bayar'])
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
            'student_id' => $studentId,
            'total_tagihan_cents' => $totalTagihan,
            'total_dibayar_cents' => $totalDibayar,
            'sisa_cents' => $totalTagihan - $totalDibayar,
            'invoices' => $invoices->map(fn ($i) => [
                'id' => $i->id,
                'bill_type' => $i->billType->name,
                'periode' => $i->periode_bulan ? "{$i->periode_bulan}/{$i->periode_tahun}" : $i->periode_tahun,
                'amount_cents' => $i->amount_cents,
                'status' => $i->status,
            ]),
        ];
    }
}
