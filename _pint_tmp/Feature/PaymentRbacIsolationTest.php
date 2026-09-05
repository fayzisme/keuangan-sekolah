<?php

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillType;
use App\Domain\Billing\Models\Payment;
use App\Domain\School\Models\School;
use App\Domain\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('bendahara can read payments but cannot verify own payment (maker-checker)', function () {
    $school = School::create(['name' => 'SMA A']);
    $bendahara = makeScopedUser($school, 'bendahara');

    Sanctum::actingAs($bendahara);

    $this->getJson('/api/v1/payments')->assertOk();
    // verify di test terpisah
});

it('murid cannot read payments', function () {
    $school = School::create(['name' => 'SMA A']);
    $murid = makeScopedUser($school, 'murid');

    Sanctum::actingAs($murid);

    $this->getJson('/api/v1/payments')->assertStatus(403);
});

it('payments index scoped to school (no cross-school leak)', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);
    $paymentB = Payment::create([
        'school_id' => $schoolB->id, 'created_by' => 1, 'method' => 'CASH', 'status' => 'SETTLED', 'total_cents' => 100,
    ]);

    Sanctum::actingAs(makeScopedUser($schoolA));

    $res = $this->getJson('/api/v1/payments')->assertOk();
    expect(collect($res->json('data'))->pluck('id')->all())->not->toContain($paymentB->id);
});

it('show payment from another school -> 404', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);
    $paymentB = Payment::create([
        'school_id' => $schoolB->id, 'created_by' => 1, 'method' => 'CASH', 'status' => 'SETTLED', 'total_cents' => 100,
    ]);

    Sanctum::actingAs(makeScopedUser($schoolA));

    $this->getJson("/api/v1/payments/{$paymentB->id}")->assertNotFound();
});

it('creator cannot verify own payment -> 409 maker-checker', function () {
    [$school, $year, $bt, $student, $invoice] = seededSchool();
    $creator = makeScopedUser($school, 'bendahara');

    Sanctum::actingAs($creator);
    $this->withHeaders(['Idempotency-Key' => 'mc-1'])
        ->postJson('/api/v1/payments/manual', ['allocations' => [['invoice_id' => $invoice->id, 'amount_cents' => 100000]]])
        ->assertCreated();

    $payment = Payment::firstOrFail();

    // Same user tries to verify -> 409
    $this->postJson("/api/v1/payments/{$payment->id}/verify")->assertStatus(409);
});

function seededSchool(string $name = 'SMA A')
{
    $school = School::create(['name' => $name]);
    $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026', 'semester' => 'ganjil']);
    $bt = BillType::create(['school_id' => $school->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);
    $student = Student::create(['school_id' => $school->id, 'nis' => 'A-1', 'name' => 'Siswa A', 'is_active' => true]);
    $invoice = BillingInvoice::create([
        'school_id' => $school->id, 'student_id' => $student->id, 'bill_type_id' => $bt->id,
        'academic_year_id' => $year->id, 'periode_bulan' => 1, 'periode_tahun' => 2025,
        'amount_cents' => 300000, 'status' => 'OPEN',
    ]);

    return [$school, $year, $bt, $student, $invoice];
}
