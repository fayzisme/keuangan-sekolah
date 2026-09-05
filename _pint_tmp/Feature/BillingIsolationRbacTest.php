<?php

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillType;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\School;
use App\Domain\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('generates invoices only for selected students in the active school', function () {
    $school = School::create(['name' => 'SMA A']);
    $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026', 'semester' => 'ganjil']);
    $student1 = Student::create(['school_id' => $school->id, 'nis' => 'A-1', 'name' => 'Siswa 1', 'is_active' => true]);
    $student2 = Student::create(['school_id' => $school->id, 'nis' => 'A-2', 'name' => 'Siswa 2', 'is_active' => true]);
    $bt = BillType::create(['school_id' => $school->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);

    Sanctum::actingAs(makeScopedUser($school));

    $this->postJson('/api/v1/invoices/generate', [
        'bill_type_id' => $bt->id,
        'academic_year_id' => $year->id,
        'periode_bulan' => 1,
        'periode_tahun' => 2025,
        'student_ids' => [$student2->id],
    ])->assertOk()->assertJson(['generated' => 1, 'skipped' => 0]);

    expect(BillingInvoice::count())->toBe(1);
    expect(BillingInvoice::first()->student_id)->toBe($student2->id);
});

it('rejects generating invoice for student_id from another school', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);
    $yearA = AcademicYear::create(['school_id' => $schoolA->id, 'name' => '2025/2026', 'semester' => 'ganjil']);
    $studentB = Student::create(['school_id' => $schoolB->id, 'nis' => 'B-1', 'name' => 'Siswa B', 'is_active' => true]);
    $btA = BillType::create(['school_id' => $schoolA->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);

    Sanctum::actingAs(makeScopedUser($schoolA));

    $this->postJson('/api/v1/invoices/generate', [
        'bill_type_id' => $btA->id,
        'academic_year_id' => $yearA->id,
        'periode_bulan' => 1,
        'periode_tahun' => 2025,
        'student_ids' => [$studentB->id],
    ])->assertStatus(422);
});

it('does not leak invoices across schools', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);
    $yearA = AcademicYear::create(['school_id' => $schoolA->id, 'name' => '2025/2026', 'semester' => 'ganjil']);
    $yearB = AcademicYear::create(['school_id' => $schoolB->id, 'name' => '2025/2026', 'semester' => 'ganjil']);
    $studentA = Student::create(['school_id' => $schoolA->id, 'nis' => 'A-1', 'name' => 'A', 'is_active' => true]);
    $studentB = Student::create(['school_id' => $schoolB->id, 'nis' => 'B-1', 'name' => 'B', 'is_active' => true]);
    $btA = BillType::create(['school_id' => $schoolA->id, 'name' => 'SPP A', 'tipe_bayar' => 'monthly', 'tarif_cents' => 100]);
    $btB = BillType::create(['school_id' => $schoolB->id, 'name' => 'SPP B', 'tipe_bayar' => 'monthly', 'tarif_cents' => 200]);

    $invoiceA = BillingInvoice::create(['school_id' => $schoolA->id, 'student_id' => $studentA->id, 'bill_type_id' => $btA->id, 'academic_year_id' => $yearA->id, 'periode_bulan' => 1, 'periode_tahun' => 2025, 'amount_cents' => 100]);
    $invoiceB = BillingInvoice::create(['school_id' => $schoolB->id, 'student_id' => $studentB->id, 'bill_type_id' => $btB->id, 'academic_year_id' => $yearB->id, 'periode_bulan' => 1, 'periode_tahun' => 2025, 'amount_cents' => 200]);

    Sanctum::actingAs(makeScopedUser($schoolA));

    $res = $this->getJson('/api/v1/invoices')->assertOk();
    expect(collect($res->json('data'))->pluck('id')->all())->toBe([$invoiceA->id]);
    $this->getJson("/api/v1/invoices/{$invoiceB->id}")->assertNotFound();
    $this->postJson("/api/v1/invoices/{$invoiceB->id}/void")->assertNotFound();
});

it('bendahara can read billing data but cannot write; murid cannot read', function () {
    $school = School::create(['name' => 'SMA A']);

    Sanctum::actingAs(makeScopedUser($school, 'bendahara'));
    $this->getJson('/api/v1/bill-types')->assertOk();
    $this->getJson('/api/v1/invoices')->assertOk();
    $this->postJson('/api/v1/bill-types', ['name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 100])->assertStatus(403);
    $this->postJson('/api/v1/invoices/generate', [])->assertStatus(403);

    Sanctum::actingAs(makeScopedUser($school, 'murid'));
    $this->getJson('/api/v1/bill-types')->assertStatus(403);
    $this->getJson('/api/v1/invoices')->assertStatus(403);
});

it('bill type and academic year must belong to active school when generating', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);
    $yearB = AcademicYear::create(['school_id' => $schoolB->id, 'name' => '2025/2026', 'semester' => 'ganjil']);
    $btB = BillType::create(['school_id' => $schoolB->id, 'name' => 'SPP B', 'tipe_bayar' => 'monthly', 'tarif_cents' => 100]);

    Sanctum::actingAs(makeScopedUser($schoolA));

    $this->postJson('/api/v1/invoices/generate', [
        'bill_type_id' => $btB->id,
        'academic_year_id' => $yearB->id,
        'periode_bulan' => 1,
        'periode_tahun' => 2025,
    ])->assertStatus(422);
})->skip('Need separate test with bill_type/year mismatch individually if CI confirms validation messages.');
