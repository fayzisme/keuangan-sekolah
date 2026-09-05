<?php

namespace App\Http\Controllers\Api;

use App\Domain\Reports\Actions\GenerateArrearsReportAction;
use App\Domain\Reports\Actions\GenerateClassReportAction;
use App\Domain\Reports\Actions\GenerateStudentReportAction;
use App\Domain\Reports\Actions\ReverseLedgerEntryAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportController extends Controller
{
    public function student(Request $request, GenerateStudentReportAction $action, int $studentId): JsonResponse
    {
        return response()->json(
            $action($request->attributes->get('school_id'), $studentId)
        );
    }

    public function class(Request $request, GenerateClassReportAction $action, int $classId): JsonResponse
    {
        return response()->json(
            $action($request->attributes->get('school_id'), $classId)
        );
    }

    public function arrears(Request $request, GenerateArrearsReportAction $action): JsonResponse
    {
        $filters = $request->only(['class_id', 'academic_year_id', 'bill_type_id']);

        return response()->json(
            $action($request->attributes->get('school_id'), $filters)
        );
    }

    public function arrearsCsv(Request $request, GenerateArrearsReportAction $action)
    {
        $filters = $request->only(['class_id', 'academic_year_id', 'bill_type_id']);
        $report = $action($request->attributes->get('school_id'), $filters);

        $csv = "NIS,Nama,Kelas,Tagihan,Tipe,Periode,Tagihan,Dibayar,Sisa\n";
        foreach ($report['data'] as $row) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%d,%d,%d\n",
                $row['nis'], $row['nama'], $row['kelas'], $row['bill_type'], $row['tipe_bayar'],
                $row['periode'], $row['tagihan_cents'], $row['dibayar_cents'], $row['sisa_cents']
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="tunggakan-'.date('Ymd').'.csv"',
        ]);
    }

    public function reverseLedger(ReverseLedgerEntryAction $action, int $id, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $entry = $action($id, $request->user()->id, $request->string('reason'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($entry, 200);
    }
}
