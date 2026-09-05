<?php

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillType;
use App\Domain\Billing\Models\Payment;
use App\Domain\Reports\Actions\GenerateArrearsReportAction;
use App\Domain\Reports\Actions\GenerateClassReportAction;
use App\Domain\Reports\Actions\GenerateStudentReportAction;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\ClassRoom;
use App\Domain\School\Models\School;
use App\Domain\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedReportData()
{
    $school = School::create(['name' => 'SMA A']);
    $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026', 'semester' => 'ganjil']);
    $class = ClassRoom::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'X IPA 1', 'level' => 10]);
    $bt = BillType::create(['school_id' => $school->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);
    $btGedung = BillType::create(['school_id' => $school->id, 'name' => 'Gedung', 'tipe_bayar' => 'one_time', 'tarif_cents' => 1500000]);
    $s1 = Student::create(['school_id' => $school->id, 'class_id' => $class->id, 'nis' => 'A-1', 'name' => 'Siswa 1', 'is_active' => true]);
    $s2 = Student::create(['school_id' => $school->id, 'class_id' => $class->id, 'nis' => 'A-2', 'name' => 'Siswa 2', 'is_active' => true]);

    $inv1 = BillingInvoice::create(['school_id' => $school->id, 'student_id' => $s1->id, 'bill_type_id' => $bt->id, 'academic_year_id' => $year->id, 'periode_bulan' => 1, 'periode_tahun' => 2025, 'amount_cents' => 300000, 'status' => 'OPEN']);
    $inv2 = BillingInvoice::create(['school_id' => $school->id, 'student_id' => $s1->id, 'bill_type_id' => $bt->id, 'academic_year_id' => $year->id, 'periode_bulan' => 2, 'periode_tahun' => 2025, 'amount_cents' => 300000, 'status' => 'OPEN']);
    $inv3 = BillingInvoice::create(['school_id' => $school->id, 'student_id' => $s2->id, 'bill_type_id' => $bt->id, 'academic_year_id' => $year->id, 'periode_bulan' => 1, 'periode_tahun' => 2025, 'amount_cents' => 300000, 'status' => 'OPEN']);
    $invG = BillingInvoice::create(['school_id' => $school->id, 'student_id' => $s1->id, 'bill_type_id' => $btGedung->id, 'academic_year_id' => $year->id, 'periode_bulan' => null, 'periode_tahun' => 2025, 'amount_cents' => 1500000, 'status' => 'OPEN']);

    // Bayar 100k untuk inv1
    $p1 = Payment::create(['school_id' => $school->id, 'created_by' => 1, 'method' => 'CASH', 'status' => Payment::STATUS_SETTLED, 'total_cents' => 100000]);
    $p1->invoices()->attach($inv1->id, ['allocated_cents' => 100000]);

    // Bayar 300k untuk inv3 (lunas)
    $p2 = Payment::create(['school_id' => $school->id, 'created_by' => 1, 'method' => 'CASH', 'status' => Payment::STATUS_SETTLED, 'total_cents' => 300000]);
    $p2->invoices()->attach($inv3->id, ['allocated_cents' => 300000]);

    return [$school, $year, $class, $bt, $btGedung, $s1, $s2, $inv1, $inv2, $inv3, $invG, $p1, $p2];
}

it('arrears report returns sisa > 0 per invoice', function () {
    seedReportData();
    $school = School::first();
    $action = new GenerateArrearsReportAction;

    $report = $action($school->id, []);

    expect($report['total_tunggakan_cents'])->toBeGreaterThan(0);
    expect(count($report['data']))->toBeGreaterThan(0);

    // inv1: 300k - 100k = 200k sisa
    $inv1Sisa = collect($report['data'])->firstWhere('invoice_id', $inv1->id);
    expect($inv1Sisa['sisa_cents'])->toBe(200000);
});

it('arrears filter by class_id', function () {
    seedReportData();
    $school = School::first();
    $class = ClassRoom::first();
    $action = new GenerateArrearsReportAction;

    $reportAll = $action($school->id, []);
    $reportClass = $action($school->id, ['class_id' => $class->id]);

    // Kelas ini cuma punya s1 dan s2 (keduanya di kelas yg sama) -> same data
    expect($reportClass['total_tunggakan_cents'])->toBe($reportAll['total_tunggakan_cents']);
});

it('arrears filter by academic_year_id', function () {
    seedReportData();
    $school = School::first();
    $year = AcademicYear::first();
    $action = new GenerateArrearsReportAction;

    $report = $action($school->id, ['academic_year_id' => $year->id]);
    expect($report['total_tunggakan_cents'])->toBeGreaterThan(0);

    // Tahun ajaran yang tidak punya invoice -> 0
    $year2 = AcademicYear::create(['school_id' => $school->id, 'name' => '2024/2025', 'semester' => 'ganjil']);
    $reportEmpty = $action($school->id, ['academic_year_id' => $year2->id]);
    expect($reportEmpty['total_tunggakan_cents'])->toBe(0);
});

it('arrears csv export has correct headers and data', function () {
    [$school, $year, $class, $bt, $btGedung, $s1, $s2, $inv1, $inv2, $inv3, $invG, $p1, $p2] = seedReportData();

    $action = new GenerateArrearsReportAction;
    $report = $action($school->id, []);

    $csv = "NIS,Nama,Kelas,Tagihan,Tipe,Periode,Tagihan,Dibayar,Sisa\n";
    foreach ($report['data'] as $row) {
        $csv .= sprintf(
            "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%d,%d,%d\n",
            $row['nis'], $row['nama'], $row['kelas'], $row['bill_type'], $row['tipe_bayar'],
            $row['periode'], $row['tagihan_cents'], $row['dibayar_cents'], $row['sisa_cents']
        );
    }

    $lines = explode("\n", trim($csv));
    expect(count($lines))->toBeGreaterThan(1); // header + data
    expect($lines[0])->toBe('NIS,Nama,Kelas,Tagihan,Tipe,Periode,Tagihan,Dibayar,Sisa');
});

it('student report calculates totals correctly', function () {
    [$school, $year, $class, $bt, $btGedung, $s1, $s2, $inv1, $inv2, $inv3, $invG, $p1, $p2] = seedReportData();
    $action = new GenerateStudentReportAction;

    $report = $action($school->id, $s1->id);

    expect($report['total_tagihan_cents'])->toBe(2400000); // 300k+300k+1.5M
    expect($report['total_dibayar_cents'])->toBe(100000); // only inv1 100k
    expect($report['sisa_cents'])->toBe(2300000);
});

it('class report sums correctly', function () {
    seedReportData();
    $school = School::first();
    $class = ClassRoom::first();
    $action = new GenerateClassReportAction;

    $report = $action($school->id, $class->id);

    expect($report['total_tagihan_cents'])->toBe(2400000); // 3 siswa x 300k + 1.5M
    expect($report['total_dibayar_cents'])->toBe(400000); // 100k + 300k
    expect($report['sisa_cents'])->toBe(2000000);
});
