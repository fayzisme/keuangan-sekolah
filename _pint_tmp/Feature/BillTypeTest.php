<?php

use App\Domain\Billing\Models\BillType;
use App\Domain\School\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('crud bill types scoped to school', function () {
    $school = School::create(['name' => 'SMA A']);
    Sanctum::actingAs(makeScopedUser($school));

    $this->postJson('/api/v1/bill-types', [
        'name' => 'SPP Bulanan',
        'tipe_bayar' => 'monthly',
        'tarif_cents' => 300000,
    ])->assertCreated()->assertJsonPath('tarif_cents', 300000);

    $bt = BillType::where('school_id', $school->id)->firstOrFail();

    $this->getJson('/api/v1/bill-types')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/bill-types/{$bt->id}")->assertOk()->assertJsonPath('name', 'SPP Bulanan');

    $this->putJson("/api/v1/bill-types/{$bt->id}", [
        'name' => 'SPP Bulanan',
        'tipe_bayar' => 'monthly',
        'tarif_cents' => 350000,
    ])->assertOk()->assertJsonPath('tarif_cents', 350000);

    $this->deleteJson("/api/v1/bill-types/{$bt->id}")->assertNoContent();
    $this->assertSoftDeleted('bill_types', ['id' => $bt->id]);
});

it('rejects duplicate bill type name within school', function () {
    $school = School::create(['name' => 'SMA A']);
    Sanctum::actingAs(makeScopedUser($school));

    $this->postJson('/api/v1/bill-types', ['name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 100])
        ->assertCreated();
    $this->postJson('/api/v1/bill-types', ['name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 100])
        ->assertStatus(422);
});

it('rejects invalid tipe_bayar and non-positive tarif', function () {
    $school = School::create(['name' => 'SMA A']);
    Sanctum::actingAs(makeScopedUser($school));

    $this->postJson('/api/v1/bill-types', ['name' => 'X', 'tipe_bayar' => 'weekly', 'tarif_cents' => 100])
        ->assertStatus(422);
    $this->postJson('/api/v1/bill-types', ['name' => 'Y', 'tipe_bayar' => 'monthly', 'tarif_cents' => 0])
        ->assertStatus(422);
    $this->postJson('/api/v1/bill-types', ['name' => 'Z', 'tipe_bayar' => 'monthly', 'tarif_cents' => -5])
        ->assertStatus(422);
});

it('404 for bill type from another school', function () {
    $schoolA = School::create(['name' => 'SMA A']);
    $schoolB = School::create(['name' => 'SMA B']);
    $btB = BillType::create(['school_id' => $schoolB->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 100]);

    Sanctum::actingAs(makeScopedUser($schoolA));

    $this->getJson("/api/v1/bill-types/{$btB->id}")->assertNotFound();
    $this->putJson("/api/v1/bill-types/{$btB->id}", ['name' => 'X', 'tipe_bayar' => 'monthly', 'tarif_cents' => 1])->assertNotFound();
    $this->deleteJson("/api/v1/bill-types/{$btB->id}")->assertNotFound();
});
