<?php

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillType;
use App\Domain\Billing\Models\Payment;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\School;
use App\Domain\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('creates manual payment with allocation to one invoice', function () {
    [$school, $year, $bt, $student, $invoice] = seededSchool();
    $creator = makeScopedUser($school, 'bendahara');

    Sanctum::actingAs($creator);

    $this->withHeaders(['Idempotency-Key' => 'test-key-1'])
        ->postJson('/api/v1/payments/manual', [
            'cashier_name' => 'Bendahara Satu',
            'allocations' => [['invoice_id' => $invoice->id, 'amount_cents' => 150000]],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'PENDING_VERIFICATION')
        ->assertJsonPath('total_cents', 150000);

    $payment = Payment::firstOrFail();
    expect($payment->invoices->count())->toBe(1);
    expect($payment->invoices->first()->pivot->allocated_cents)->toBe(150000);
});

it('creates manual payment allocating to multiple invoices', function () {
    $school = School::create(['name' => 'SMA A']);
    $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026', 'semester' => 'ganjil']);
    $bt = BillType::create(['school_id' => $school->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);
    $student = Student::create(['school_id' => $school->id, 'nis' => 'A-1', 'name' => 'Siswa A', 'is_active' => true]);
    $inv1 = BillingInvoice::create(['school_id' => $school->id, 'student_id' => $student->id, 'bill_type_id' => $bt->id, 'academic_year_id' => $year->id, 'periode_bulan' => 1, 'periode_tahun' => 2025, 'amount_cents' => 300000, 'status' => 'OPEN']);
    $inv2 = BillingInvoice::create(['school_id' => $school->id, 'student_id' => $student->id, 'bill_type_id' => $bt->id, 'academic_year_id' => $year->id, 'periode_bulan' => 2, 'periode_tahun' => 2025, 'amount_cents' => 300000, 'status' => 'OPEN']);

    Sanctum::actingAs(makeScopedUser($school, 'bendahara'));

    $this->withHeaders(['Idempotency-Key' => 'multi-inv-1'])
        ->postJson('/api/v1/payments/manual', [
            'allocations' => [
                ['invoice_id' => $inv1->id, 'amount_cents' => 200000],
                ['invoice_id' => $inv2->id, 'amount_cents' => 100000],
            ],
        ])->assertCreated()
        ->assertJsonPath('total_cents', 300000);

    $payment = Payment::firstOrFail();
    expect($payment->invoices->count())->toBe(2);
    expect($payment->total_cents)->toBe(300000);
});

it('rejects duplicate idempotency key (409)', function () {
    [$school, $year, $bt, $student, $invoice] = seededSchool();

    Sanctum::actingAs(makeScopedUser($school, 'bendahara'));

    $key = 'idem-key-dup';
    $this->withHeaders(['Idempotency-Key' => $key])
        ->postJson('/api/v1/payments/manual', ['allocations' => [['invoice_id' => $invoice->id, 'amount_cents' => 100000]]])
        ->assertCreated();

    $this->withHeaders(['Idempotency-Key' => $key])
        ->postJson('/api/v1/payments/manual', ['allocations' => [['invoice_id' => $invoice->id, 'amount_cents' => 100000]]])
        ->assertStatus(409);
});

it('rejects allocation to invoice from another school (422)', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);
    $yearB = AcademicYear::create(['school_id' => $schoolB->id, 'name' => '2025/2026', 'semester' => 'ganjil']);
    $btB = BillType::create(['school_id' => $schoolB->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);
    $studentB = Student::create(['school_id' => $schoolB->id, 'nis' => 'B-1', 'name' => 'B', 'is_active' => true]);
    $invB = BillingInvoice::create(['school_id' => $schoolB->id, 'student_id' => $studentB->id, 'bill_type_id' => $btB->id, 'academic_year_id' => $yearB->id, 'periode_bulan' => 1, 'periode_tahun' => 2025, 'amount_cents' => 300000, 'status' => 'OPEN']);

    Sanctum::actingAs(makeScopedUser($schoolA, 'bendahara'));

    $this->postJson('/api/v1/payments/manual', [
        'allocations' => [['invoice_id' => $invB->id, 'amount_cents' => 100000]],
    ])->assertStatus(422);
});

it('rejects allocation amount > invoice amount (422)', function () {
    [$school, $year, $bt, $student, $invoice] = seededSchool();

    Sanctum::actingAs(makeScopedUser($school, 'bendahara'));

    $this->postJson('/api/v1/payments/manual', [
        'allocations' => [['invoice_id' => $invoice->id, 'amount_cents' => 999999]],
    ])->assertStatus(422);
});

it('rejects empty allocations (422)', function () {
    $school = School::create(['name' => 'SMA A']);
    Sanctum::actingAs(makeScopedUser($school, 'bendahara'));

    $this->postJson('/api/v1/payments/manual', ['allocations' => []])->assertStatus(422);
});

it('verifies payment by DIFFERENT user -> SETTLED + ledger + receipt + invoice PAID', function () {
    [$school, $year, $bt, $student, $invoice] = seededSchool();
    $creator = makeScopedUser($school, 'bendahara');
    $verifier = makeScopedUser($school, 'bendahara'); // role same, user different

    Sanctum::actingAs($creator);
    $this->withHeaders(['Idempotency-Key' => 'verify-1'])
        ->postJson('/api/v1/payments/manual', ['allocations' => [['invoice_id' => $invoice->id, 'amount_cents' => 300000]]])
        ->assertCreated();

    $payment = Payment::firstOrFail();

    Sanctum::actingAs($verifier);
    $this->postJson("/api/v1/payments/{$payment->id}/verify")
        ->assertOk()
        ->assertJsonPath('status', 'SETTLED');

    $payment->refresh();
    expect($payment->status)->toBe(Payment::STATUS_SETTLED);
    expect($payment->verified_by)->toBe($verifier->id);
    expect($invoice->fresh()->status)->toBe('PAID');

    // Ledger entry created
    $this->assertDatabaseHas('ledger_entries', [
        'school_id' => $school->id,
        'ref_type' => 'payment',
        'ref_id' => $payment->id,
        'credit_cents' => 300000,
    ]);

    // Receipt created
    $this->assertDatabaseHas('receipts', ['payment_id' => $payment->id]);
});
