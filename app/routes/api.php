<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillTypeController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\GuardianController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OnboardController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'School Finance API v1 siap.',
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // Grup terautentikasi TANPA school.context:
    // me/logout/switch harus tetap bisa diakses user yang belum punya sekolah aktif
    // (mis. baru daftar / semua pivot nonaktif) — jika digabung dgn school.context,
    // user akan lockout (tidak bisa switch ataupun logout). Throttle 60/menit sbg lapis kedua.
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/switch-school', [AuthController::class, 'switchSchool']);
    });

    // Endpoint yang membutuhkan konteks sekolah aktif + RBAC.
    Route::middleware(['auth:sanctum', 'school.context'])->group(function () {
        Route::get('/users', [AuthController::class, 'users'])->middleware('role:admin|bendahara');
    });
});

// ===== Onboarding sekolah — level platform (bukan konteks sekolah) =====
// Dijaga platform.key (X-Platform-Key header). Tanpa PLATFORM_KEY di env → 503.
Route::middleware(['platform.key', 'throttle:10,1'])->group(function () {
    Route::post('/platform/schools', [OnboardController::class, 'store']);
});

// ===== Master data — konteks sekolah aktif + RBAC =====
// Read: admin|bendahara ; write: admin. Tenant scope dijamin middleware school.context.
Route::middleware(['auth:sanctum', 'school.context'])->group(function () {
    Route::middleware('role:admin|bendahara')->group(function () {
        Route::get('/academic-years', [AcademicYearController::class, 'index']);
        Route::get('/academic-years/{id}', [AcademicYearController::class, 'show'])->whereNumber('id');

        Route::get('/classes', [ClassController::class, 'index']);
        Route::get('/classes/{id}', [ClassController::class, 'show'])->whereNumber('id');

        Route::get('/students', [StudentController::class, 'index']);
        Route::get('/students/{id}', [StudentController::class, 'show'])->whereNumber('id');

        Route::get('/guardians', [GuardianController::class, 'index']);
        Route::get('/guardians/{id}', [GuardianController::class, 'show'])->whereNumber('id');

        // Billing
        Route::get('/bill-types', [BillTypeController::class, 'index']);
        Route::get('/bill-types/{id}', [BillTypeController::class, 'show'])->whereNumber('id');

        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->whereNumber('id');

        // Payments (read)
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{id}', [PaymentController::class, 'show'])->whereNumber('id');

        // Payments (write) — maker & checker sama grup: bendahara catat manual,
        // verify pembayaran user LAIN (role admin|bendahara). Guard
        // "ne peut pas verifier propre paiement" reside di controller.
        Route::post('/payments/manual', [PaymentController::class, 'store']);
        Route::post('/payments/{id}/verify', [PaymentController::class, 'verify'])->whereNumber('id');

        // Reports (read)
        Route::get('/reports/student/{studentId}', [ReportController::class, 'student'])->whereNumber('studentId');
        Route::get('/reports/class/{classId}', [ReportController::class, 'class'])->whereNumber('classId');
        Route::get('/reports/arrears', [ReportController::class, 'arrears']);
        Route::get('/reports/arrears.csv', [ReportController::class, 'arrearsCsv']);

        // Exports
        Route::get('/exports/student/{studentId}/pdf', [ExportController::class, 'studentPdf'])->whereNumber('studentId');
        Route::get('/exports/arrears/pdf', [ExportController::class, 'arrearsPdf']);
        Route::get('/exports/arrears/excel', [ExportController::class, 'arrearsExcel']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::post('/academic-years', [AcademicYearController::class, 'store']);
        Route::put('/academic-years/{id}', [AcademicYearController::class, 'update'])->whereNumber('id');
        Route::delete('/academic-years/{id}', [AcademicYearController::class, 'destroy'])->whereNumber('id');

        Route::post('/classes', [ClassController::class, 'store']);
        Route::put('/classes/{id}', [ClassController::class, 'update'])->whereNumber('id');
        Route::delete('/classes/{id}', [ClassController::class, 'destroy'])->whereNumber('id');

        Route::post('/students', [StudentController::class, 'store']);
        Route::put('/students/{id}', [StudentController::class, 'update'])->whereNumber('id');
        Route::delete('/students/{id}', [StudentController::class, 'destroy'])->whereNumber('id');
        Route::post('/students/{id}/guardians', [StudentController::class, 'attachGuardian'])->whereNumber('id');
        Route::delete('/students/{id}/guardians/{guardianId}', [StudentController::class, 'detachGuardian'])->whereNumber('id')->whereNumber('guardianId');

        Route::post('/guardians', [GuardianController::class, 'store']);
        Route::put('/guardians/{id}', [GuardianController::class, 'update'])->whereNumber('id');
        Route::delete('/guardians/{id}', [GuardianController::class, 'destroy'])->whereNumber('id');

        // Billing
        Route::post('/bill-types', [BillTypeController::class, 'store']);
        Route::put('/bill-types/{id}', [BillTypeController::class, 'update'])->whereNumber('id');
        Route::delete('/bill-types/{id}', [BillTypeController::class, 'destroy'])->whereNumber('id');

        Route::post('/invoices/generate', [InvoiceController::class, 'generate']);
        Route::post('/invoices/{id}/void', [InvoiceController::class, 'void'])->whereNumber('id');

        // Payments (write) — verify sudah di grup role:admin|bendahara.

        // Reports (write)
        Route::post('/reports/ledger/{id}/reverse', [ReportController::class, 'reverseLedger'])->whereNumber('id');
    });
});
