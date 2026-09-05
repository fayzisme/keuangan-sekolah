<?php

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillType;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\ClassRoom;
use App\Domain\School\Models\School;
use App\Domain\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function seedExportData()
{
    $school = School::create(['name' => 'SMA A']);
    $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026', 'semester' => 'ganjil']);
    $class = ClassRoom::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'X IPA 1', 'level' => 10]);
    $bt = BillType::create(['school_id' => $school->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);
    $btG = BillType::create(['school_id' => $school->id, 'name' => 'Gedung', 'tipe_bayar' => 'one_time', 'tarif_cents' => 1500000]);
    $s1 = Student::create(['school_id' => $school->id, 'class_id' => $class->id, 'nis' => 'A-1', 'name' => 'Siswa Satu', 'is_active' => true]);
    $s2 = Student::create(['school_id' => $school->id, 'class_id' => $class->id, 'nis' => 'A-2', 'name' => 'Siswa Dua', 'is_active' => true]);

    $inv1 = BillingInvoice::create(['school_id' => $school->id, 'student_id' => $s1->id, 'bill_type_id' => $bt->id, 'academic_year_id' => $year->id, 'periode_bulan' => 1, 'periode_tahun' => 2025, 'amount_cents' => 300000, 'status' => 'OPEN']);
    $inv2 = BillingInvoice::create(['school_id' => $school->id, 'student_id' => $s1->id, 'bill_type_id' => $bt->id, 'academic_year_id' => $year->id, 'periode_bulan' => 2, 'periode_tahun' => 2025, 'amount_cents' => 300000, 'status' => 'PARTIAL']);
    $inv3 = BillingInvoice::create(['school_id' => $school->id, 'student_id' => $s2->id, 'bill_type_id' => $bt->id, 'academic_year_id' => $year->id, 'periode_bulan' => 1, 'periode_tahun' => 2025, 'amount_cents' => 300000, 'status' => 'OPEN']);

    return [$school, $year, $class, $bt, $s1, $s2, $inv1, $inv2, $inv3];
}

it('exports student PDF via endpoint', function () {
    [$school, $year, $class, $bt, $s1, $s2, $inv1, $inv2, $inv3] = seedExportData();
    Sanctum::actingAs(makeScopedUser($school, 'bendahara'));

    $response = $this->getJson("/api/v1/exports/student/{$s1->id}/pdf");
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeaderContains('Content-Disposition', 'attachment; filename=laporan-siswa-A-1-');
});

it('exports arrears PDF via endpoint', function () {
    seedExportData();
    $school = School::first();
    Sanctum::actingAs(makeScopedUser($school, 'bendahara'));

    $response = $this->getJson('/api/v1/exports/arrears/pdf');
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeaderContains('Content-Disposition', 'attachment; filename=laporan-tunggakan-SMA A-');
});

it('exports arrears Excel via endpoint', function () {
    seedExportData();
    $school = School::first();
    Sanctum::actingAs(makeScopedUser($school, 'bendahara'));

    $response = $this->getJson('/api/v1/exports/arrears/excel');
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeaderContains('Content-Disposition', 'attachment; filename=tunggakan-SMA A-');
});

it('rejects export from different school (tenant isolation)', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);
    $yearB = AcademicYear::create(['school_id' => $schoolB->id, 'name' => '2025/2026', 'semester' => 'ganjil']);
    $classB = ClassRoom::create(['school_id' => $schoolB->id, 'academic_year_id' => $yearB->id, 'name' => 'X-1']);
    $btB = BillType::create(['school_id' => $schoolB->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);
    $studentB = Student::create(['school_id' => $schoolB->id, 'class_id' => $classB->id, 'nis' => 'B-1', 'name' => 'B', 'is_active' => true]);

    Sanctum::actingAs(makeScopedUser($schoolA, 'bendahara'));

    $this->getJson("/api/v1/exports/student/{$studentB->id}/pdf")->assertNotFound();
    $this->getJson('/api/v1/exports/arrears/pdf?school_id='.$schoolB->id)->assertStatus(403);
});
