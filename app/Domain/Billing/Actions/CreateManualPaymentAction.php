<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CreateManualPaymentAction
{
    /**
     * Catat pembayaran tunai manual (PENDING_VERIFICATION) + alokasi ke 1..N invoice.
     *
     * @param array{
     *   school_id: int,
     *   created_by: int,
     *   cashier_name?: string,
     *   proof_path?: string,
     *   idempotency_key?: string,
     *   allocations: array<array{invoice_id: int, amount_cents: int}>
     * } $data
     */
    public function __invoke(array $data): Payment
    {
        $schoolId = $data['school_id'];
        $allocations = $data['allocations'];

        if (empty($allocations)) {
            throw new RuntimeException('Alokasi pembayaran ke invoice tidak boleh kosong.');
        }

        $totalCents = array_sum(array_column($allocations, 'amount_cents'));

        // Idempotency: hash gateway_trx_id dari idempotency-key (atau fallback random)
        $key = $data['idempotency_key'] ?? null;
        $trxId = $key ? 'CASH-' . hash('sha256', $schoolId . ':' . $key) : null;

        if ($trxId && Payment::where('gateway_trx_id', $trxId)->exists()) {
            throw new RuntimeException('Pembayaran dengan Idempotency-Key ini sudah dicatat.', 409);
        }

        return DB::transaction(function () use ($schoolId, $data, $totalCents, $allocations, $trxId) {
            $payment = Payment::create([
                'school_id' => $schoolId,
                'created_by' => $data['created_by'],
                'method' => Payment::METHOD_CASH,
                'status' => Payment::STATUS_PENDING_VERIFICATION,
                'total_cents' => $totalCents,
                'proof_path' => $data['proof_path'] ?? null,
                'cashier_name' => $data['cashier_name'] ?? null,
                'gateway_trx_id' => $trxId,
            ]);

            foreach ($allocations as $alloc) {
                $invoice = BillingInvoice::query()
                    ->where('school_id', $schoolId)
                    ->whereKey($alloc['invoice_id'])
                    ->firstOrFail();

                if (in_array($invoice->status, [BillingInvoice::STATUS_PAID, BillingInvoice::STATUS_VOID], true)) {
                    throw new RuntimeException("Invoice {$invoice->id} sudah lunas/void.");
                }

                $payment->invoices()->attach($invoice->id, [
                    'allocated_cents' => $alloc['amount_cents'],
                ]);
            }

            return $payment->fresh(['invoices']);
        });
    }
}
