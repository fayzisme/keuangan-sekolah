<?php

namespace App\Http\Controllers\Api;

use App\Domain\Reports\Actions\ExportArrearsExcelAction;
use App\Domain\Reports\Actions\ExportArrearsReportAction;
use App\Domain\Reports\Actions\ExportStudentReportAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportController extends Controller
{
    public function studentPdf(Request $request, ExportStudentReportAction $action, int $studentId): StreamedResponse
    {
        $schoolId = $request->attributes->get('school_id');
        $result = $action($schoolId, $studentId);

        return response()->streamDownload(
            fn () => print($result['content']),
            $result['filename'],
            ['Content-Type' => $result['mime']]
        );
    }

    public function arrearsPdf(Request $request, ExportArrearsReportAction $action): StreamedResponse
    {
        $schoolId = $request->attributes->get('school_id');
        $filters = $request->only(['class_id', 'academic_year_id', 'bill_type_id']);
        $result = $action($schoolId, $filters);

        return response()->streamDownload(
            fn () => print($result['content']),
            $result['filename'],
            ['Content-Type' => $result['mime']]
        );
    }

    public function arrearsExcel(Request $request, ExportArrearsExcelAction $action): StreamedResponse
    {
        $schoolId = $request->attributes->get('school_id');
        $filters = $request->only(['class_id', 'academic_year_id', 'bill_type_id']);
        $result = $action($schoolId, $filters);

        return response()->streamDownload(
            fn () => readfile($result['path']),
            $result['filename'],
            ['Content-Type' => $result['mime']]
        );
    }

    public function arrearsPdfDirect(Request $request, ExportArrearsReportAction $action): JsonResponse
    {
        // Alternative: return base64 encoded PDF (for frontend display)
        $schoolId = $request->attributes->get('school_id');
        $filters = $request->only(['class_id', 'academic_year_id', 'bill_type_id']);
        $result = $action($schoolId, $filters);

        return response()->json([
            'filename' => $result['filename'],
            'base64' => base64_encode($result['content']),
            'mime' => $result['mime'],
        ]);
    }
}
