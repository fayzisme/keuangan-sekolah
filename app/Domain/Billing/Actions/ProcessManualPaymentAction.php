<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\Receipt;
use App\Infrastructure\Finance\LedgerRepository;
use App\Infrastructure\Finance\ReceiptSequenceRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ProcessManualPaymentAction
{
    public function __construct(
        private readonly LedgerRepository $ledger,
        private readonly ReceiptSequenceRepository $receiptSequences,
    ) {}

    public function __invoke(Payment $payment, int $verifiedByUserId): Payment
    {
        // Maker-checker enforce di level action (ADR-0013)
        if ($payment->created_by === $verifiedByUserId) {
            throw new RuntimeException('maker-checker: pencatat tidak boleh memverifikasi pembayaran sendiri.', 409);
        }

        if ($payment->status !== Payment::STATUS_PENDING_VERIFICATION) {
            throw new RuntimeException("Payment {$payment->id} bukan PENDING_VERIFICATION (status={$payment->status}).", 409);
        }

        return DB::transaction(function () use ($payment, $verifiedByUserId) {
            // Lock semua invoice yang teralokasi
            $allocations = DB::table('payment_invoice')
                ->where('payment_id', $payment->id)
                ->get();

            $academicYearId = null;

            foreach ($allocations as $alloc) {
                $invoice = BillingInvoice::query()
                    ->whereKey($alloc->invoice_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $academicYearId = $invoice->academic_year_id;

                if (in_array($invoice->status, [BillingInvoice::STATUS_PAID, BillingInvoice::STATUS_VOID], true)) {
                    throw new RuntimeException("Invoice {$invoice->id} sudah lunas atau void.");
                }

                // Saldo terbayar sebelumnya
                $alreadyPaid = DB::table('payment_invoice')
                    ->join('payments', 'payments.id', '=', 'payment_invoice.payment_id')
                    ->where('payment_invoice.invoice_id', $invoice->id)
                    ->where('payments.status', Payment::STATUS_SETTLED)
                    ->sum('payment_invoice.allocated_cents');

                $newTotal = $alreadyPaid + $alloc->allocated_cents;

                if ($newTotal > $invoice->amount_cents) {
                    throw new RuntimeException("Overpayment pada invoice {$invoice->id}.");
                }

                $newStatus = ($newTotal >= $invoice->amount_cents)
                    ? BillingInvoice::STATUS_PAID
                    : BillingInvoice::STATUS_PARTIAL;

                $invoice->update(['status' => $newStatus]);
            }

            // Set status payment settled
            $payment->update([
                'status' => Payment::STATUS_SETTLED,
                'verified_by' => $verifiedByUserId,
                'verified_at' => now(),
            ]);

            // Ledger entry append-only
            $this->ledger->append(
                schoolId: $payment->school_id,
                refType: 'payment',
                refId: $payment->id,
                creditCents: $payment->total_cents,
                note: "Pembayaran tunai #{$payment->id}",
                createdBy: $verifiedByUserId,
            );

            // Receipt number
            if ($academicYearId) {
                $number = $this->receiptSequences->nextForSchoolAndYear($payment->school_id, $academicYearId);
                Receipt::create([
                    'school_id' => $payment->school_id,
                    'academic_year_id' => $academicYearId,
                    'payment_id' => $payment->id,
                    'number' => $number,
                ]);
            }

            return $payment->fresh(['invoices', 'receipt']);
        });
    }
}
