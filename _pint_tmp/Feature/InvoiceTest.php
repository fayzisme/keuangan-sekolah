<?php

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillType;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\School;
use App\Domain\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function seededBillingSchool(string $name = 'SMA A')
{
    $school = School::create(['name' => $name]);
    $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026', 'semester' => 'ganjil']);
    Student::create(['school_id' => $school->id, 'nis' => 'A-1', 'name' => 'Siswa Aktif', 'is_active' => true]);
    Student::create(['school_id' => $school->id, 'nis' => 'A-2', 'name' => 'Siswa Nonaktif', 'is_active' => false]);

    return [$school, $year];
}

it('generates monthly invoices for all active students, amount from master', function () {
    [$school, $year] = seededBillingSchool();
    $bt = BillType::create(['school_id' => $school->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);

    Sanctum::actingAs(makeScopedUser($school));

    $res = $this->postJson('/api/v1/invoices/generate', [
        'bill_type_id' => $bt->id,
        'academic_year_id' => $year->id,
        'periode_bulan' => 1,
        'periode_tahun' => 2025,
    ]);

    $res->assertOk()->assertJson([
        'generated' => 1, // HANYA siswa aktif (1 dari 2)
        'skipped' => 0,
    ]);

    $invoice = BillingInvoice::where('school_id', $school->id)->firstOrFail();
    expect($invoice->amount_cents)->toBe(300000); // dari tarif master
    expect($invoice->student->nis)->toBe('A-1');
    expect($invoice->status)->toBe('OPEN');
    expect($invoice->periode_bulan)->toBe(1);
});

it('is idempotent - double generate skips existing (anti double-invoice)', function () {
    [$school, $year] = seededBillingSchool();
    $bt = BillType::create(['school_id' => $school->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);

    Sanctum::actingAs(makeScopedUser($school));

    $payload = ['bill_type_id' => $bt->id, 'academic_year_id' => $year->id, 'periode_bulan' => 1, 'periode_tahun' => 2025];

    $this->postJson('/api/v1/invoices/generate', $payload)->assertOk()->assertJson(['generated' => 1, 'skipped' => 0]);
    $this->postJson('/api/v1/invoices/generate', $payload)->assertOk()->assertJson(['generated' => 0, 'skipped' => 1]);

    expect(BillingInvoice::count())->toBe(1);
});

it('one_time - double generate same tahun yields single invoice (NULLS NOT DISTINCT)', function () {
    [$school, $year] = seededBillingSchool();
    $bt = BillType::create(['school_id' => $school->id, 'name' => 'Uang Gedung', 'tipe_bayar' => 'one_time', 'tarif_cents' => 1500000]);

    Sanctum::actingAs(makeScopedUser($school));

    $payload = ['bill_type_id' => $bt->id, 'academic_year_id' => $year->id, 'periode_tahun' => 2025];

    // one_time: tanpa periode_bulan
    $this->postJson('/api/v1/invoices/generate', $payload)->assertOk()->assertJson(['generated' => 1, 'skipped' => 0]);
    $this->postJson('/api/v1/invoices/generate', $payload)->assertOk()->assertJson(['generated' => 0, 'skipped' => 1]);

    expect(BillingInvoice::count())->toBe(1);
    expect(BillingInvoice::first()->periode_bulan)->toBeNull();
});

it('client cannot influence amount - ignores amount_cents in body', function () {
    [$school, $year] = seededBillingSchool();
    $bt = BillType::create(['school_id' => $school->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);

    Sanctum::actingAs(makeScopedUser($school));

    $this->postJson('/api/v1/invoices/generate', [
        'bill_type_id' => $bt->id,
        'academic_year_id' => $year->id,
        'periode_bulan' => 2,
        'periode_tahun' => 2025,
        'amount_cents' => 1, // client coba set nominal → diabaikan
    ])->assertOk();

    $invoice = BillingInvoice::firstOrFail();
    expect($invoice->amount_cents)->toBe(300000); // tetap tarif master
});

it('requires periode_bulan for monthly, forbids for one_time', function () {
    [$school, $year] = seededBillingSchool();
    $btM = BillType::create(['school_id' => $school->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);
    $btO = BillType::create(['school_id' => $school->id, 'name' => 'Gedung', 'tipe_bayar' => 'one_time', 'tarif_cents' => 1500000]);

    Sanctum::actingAs(makeScopedUser($school));

    // monthly tanpa periode_bulan → 422
    $this->postJson('/api/v1/invoices/generate', ['bill_type_id' => $btM->id, 'academic_year_id' => $year->id, 'periode_tahun' => 2025])
        ->assertStatus(422);

    // one_time dengan periode_bulan → 422
    $this->postJson('/api/v1/invoices/generate', ['bill_type_id' => $btO->id, 'academic_year_id' => $year->id, 'periode_bulan' => 3, 'periode_tahun' => 2025])
        ->assertStatus(422);
});

it('void only open invoice; returns 409 otherwise', function () {
    [$school, $year] = seededBillingSchool();
    $bt = BillType::create(['school_id' => $school->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);

    Sanctum::actingAs(makeScopedUser($school));

    $this->postJson('/api/v1/invoices/generate', ['bill_type_id' => $bt->id, 'academic_year_id' => $year->id, 'periode_bulan' => 1, 'periode_tahun' => 2025])->assertOk();

    $invoice = BillingInvoice::firstOrFail();

    $this->postJson("/api/v1/invoices/{$invoice->id}/void")->assertOk()->assertJsonPath('invoice.status', 'VOID');

    // void lagi (sudah VOID) → 409
    $this->postJson("/api/v1/invoices/{$invoice->id}/void")->assertStatus(409);
});
