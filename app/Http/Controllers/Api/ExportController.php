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
    /**
     * Tenant guard: pemanggil tidak boleh memaksa konteks sekolah via query
     * `?school_id=`. Sekolah aktif selalu dari middleware school.context
     * (attributes), param query yang tak cocok ditolak 403.
     */
    private function ensureNoForcedSchool(Request $request): void
    {
        $forced = $request->query('school_id');
        if ($forced !== null && (int) $forced !== (int) $request->attributes->get('school_id')) {
            abort(403, 'Param school_id tidak diizinkan pada ekspor.');
        }
    }

    public function studentPdf(Request $request, ExportStudentReportAction $action, int $studentId): StreamedResponse
    {
        $this->ensureNoForcedSchool($request);
        $schoolId = $request->attributes->get('school_id');
        $result = $action($schoolId, $studentId);

        return response()->streamDownload(
            fn () => print ($result['content']),
            $result['filename'],
            ['Content-Type' => $result['mime']]
        );
    }

    public function arrearsPdf(Request $request, ExportArrearsReportAction $action): StreamedResponse
    {
        $this->ensureNoForcedSchool($request);
        $schoolId = $request->attributes->get('school_id');
        $filters = $request->only(['class_id', 'academic_year_id', 'bill_type_id']);
        $result = $action($schoolId, $filters);

        return response()->streamDownload(
            fn () => print ($result['content']),
            $result['filename'],
            ['Content-Type' => $result['mime']]
        );
    }

    public function arrearsExcel(Request $request, ExportArrearsExcelAction $action): StreamedResponse
    {
        $this->ensureNoForcedSchool($request);
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
