<?php

namespace App\Domain\Reports\Actions;

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\School\Models\School;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ExportArrearsExcelAction
{
    public function __invoke(int $schoolId, array $filters = []): array
    {
        $school = School::findOrFail($schoolId);

        $query = BillingInvoice::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', ['OPEN', 'PARTIAL']);

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

        // Create spreadsheet
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tunggakan');

        // Header
        $headers = ['No', 'NIS', 'Nama Siswa', 'Kelas', 'Tagihan', 'Tipe', 'Periode', 'Tagihan (Rp)', 'Sisa (Rp)'];
        $sheet->fromArray([$headers], null, 'A1');

        // Style header
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
        $sheet->getStyle('A1:I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Data
        $row = 2;
        $totalSisa = 0;
        foreach ($results as $index => $r) {
            $sheet->setCellValue("A{$row}", $index + 1);
            $sheet->setCellValue("B{$row}", $r['nis']);
            $sheet->setCellValue("C{$row}", $r['nama']);
            $sheet->setCellValue("D{$row}", $r['kelas']);
            $sheet->setCellValue("E{$row}", $r['bill_type']);
            $sheet->setCellValue("F{$row}", $r['tipe_bayar']);
            $sheet->setCellValue("G{$row}", $r['periode']);
            $sheet->setCellValue("H{$row}", $r['tagihan_cents'] / 100);
            $sheet->setCellValue("I{$row}", $r['sisa_cents'] / 100);

            // Format currency columns
            $sheet->getStyle("H{$row}:I{$row}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("H{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $totalSisa += $r['sisa_cents'];
            $row++;
        }

        // Total row
        $sheet->setCellValue("G{$row}", 'TOTAL');
        $sheet->getStyle("G{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->setCellValue("H{$row}", array_sum(array_column($results, 'tagihan_cents')) / 100);
        $sheet->setCellValue("I{$row}", $totalSisa / 100);
        $sheet->getStyle("H{$row}:I{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("H{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Auto size columns
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Write to temporary file
        $writer = new Xlsx($spreadsheet);
        $tempPath = sys_get_temp_dir()."/tunggakan-{$school->name}-".date('Ymd').'.xlsx';
        $writer->save($tempPath);

        return [
            'filename' => "tunggakan-{$school->name}-".date('Ymd').'.xlsx',
            'path' => $tempPath,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }
}
