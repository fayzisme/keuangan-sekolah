<?php

namespace App\Domain\Reports\Actions;

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\School\Models\School;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use Illuminate\Support\Facades\DB;

final class ExportArrearsReportAction
{
    public function __invoke(int $schoolId, array $filters = []): array
    {
        $school = School::findOrFail($schoolId);

        $query = BillingInvoice::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', [BillingInvoice::STATUS_OPEN, BillingInvoice::STATUS_PARTIAL]);

        if (! empty($filters['class_id'])) {
            $query->whereHas('student', fn ($q) => $q->where('class_id', $filters['class_id']));
        }

        if (! empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (! empty($filters['bill_type_id'])) {
            $query->where('bill_type_id', $filters['bill_type_id']);
        }

        $invoices = $query->with(['student:id,name,nis,class_id', 'billType:id,name,tipe_bayar'])->get();

        $results = [];
        foreach ($invoices as $inv) {
            $paid = DB::table('payment_invoice')
                ->join('payments', 'payments.id', '=', 'payment_invoice.payment_id')
                ->where('payment_invoice.invoice_id', $inv->id)
                ->where('payments.status', Payment::STATUS_SETTLED)
                ->sum('payment_invoice.allocated_cents');

            $sisa = $inv->amount_cents - $paid;

            if ($sisa <= 0) {
                continue;
            }

            $results[] = [
                'invoice_id' => $inv->id,
                'student_id' => $inv->student_id,
                'nis' => $inv->student->nis,
                'nama' => $inv->student->name,
                'kelas' => $inv->student->classRoom?->name ?? '',
                'bill_type' => $inv->billType->name,
                'tipe_bayar' => $inv->billType->tipe_bayar,
                'periode' => $inv->periode_bulan ? "{$inv->periode_bulan}/{$inv->periode_tahun}" : (string) $inv->periode_tahun,
                'tagihan_cents' => $inv->amount_cents,
                'dibayar_cents' => $paid,
                'sisa_cents' => $sisa,
            ];
        }

        $totalTunggakan = array_sum(array_column($results, 'sisa_cents'));

        // Generate PDF
        $pdf = DomPDF::loadView('reports.pdf.arrears', [
            'school_name' => $school->name,
            'data' => $results,
            'total_tunggakan_cents' => $totalTunggakan,
        ])->setPaper('a4', 'landscape');

        return [
            'filename' => "laporan-tunggakan-{$school->name}-".date('Ymd').'.pdf',
            'content' => $pdf->output(),
            'mime' => 'application/pdf',
        ];
    }
}
