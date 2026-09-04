<?php

namespace App\Domain\Reports\Actions;

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\School\Models\School;
use App\Domain\Student\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;

final class ExportStudentReportAction
{
    public function __invoke(int $schoolId, int $studentId): array
    {
        $school = School::findOrFail($schoolId);
        $student = Student::query()
            ->where('school_id', $schoolId)
            ->whereKey($studentId)
            ->with('classRoom:id,name')
            ->firstOrFail();

        $invoices = BillingInvoice::query()
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->with('billType:id,name,tipe_bayar')
            ->get();

        $totalTagihan = $invoices->sum('amount_cents');
        $totalDibayar = 0;
        $data = [];

        foreach ($invoices as $inv) {
            $paid = DB::table('payment_invoice')
                ->join('payments', 'payments.id', '=', 'payment_invoice.payment_id')
                ->where('payment_invoice.invoice_id', $inv->id)
                ->where('payments.status', Payment::STATUS_SETTLED)
                ->sum('payment_invoice.allocated_cents');

            $totalDibayar += $paid;
            $data[] = [
                'bill_type' => $inv->billType->name,
                'periode' => $inv->periode_bulan ? "{$inv->periode_bulan}/{$inv->periode_tahun}" : (string) $inv->periode_tahun,
                'amount_cents' => $inv->amount_cents,
                'status' => $inv->status,
            ];
        }

        $sisa = $totalTagihan - $totalDibayar;

        // Generate PDF
        $pdf = DomPDF::loadView('reports.pdf.student', [
            'school_name' => $school->name,
            'student_name' => $student->name,
            'nis' => $student->nis,
            'class_name' => $student->classRoom?->name ?? '-',
            'total_tagihan_cents' => $totalTagihan,
            'total_dibayar_cents' => $totalDibayar,
            'sisa_cents' => $sisa,
            'invoices' => $data,
        ])->setPaper('a4', 'portrait');

        return [
            'filename' => "laporan-siswa-{$student->nis}-" . date('Ymd') . ".pdf",
            'content' => $pdf->output(),
            'mime' => 'application/pdf',
        ];
    }
}
