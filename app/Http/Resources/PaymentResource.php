<?php

namespace App\Http\Resources;

use App\Domain\Billing\Models\Payment;

/**
 * PaymentResource — output shape API (konsumend frontend/OpenAPI).
 * Jangan leak internal: proof_path internal, hanya url akses-bersetuh.
 */
final class PaymentResource
{
    public function __construct(private readonly Payment $payment) {}

    public function toArray(): array
    {
        return [
            'id'                 => $this->payment->id,
            'invoice_id'         => $this->payment->invoice_id,
            'method'             => $this->payment->method,
            'status'             => $this->payment->status,
            'amount_cents'       => $this->payment->amount_cents,   // integer cents
            'proof_url'          => $this->payment->proof_path
                ? \sprintf('/api/v1/payments/%d/proof', $this->payment->id)
                : null,
            'created_by'         => $this->payment->created_by,
            'verified_by'        => $this->payment->verified_by,
            'verified_at'        => $this->payment->verified_at?->toIso8601String(),
            'receipt_number'     => $this->payment->receipt?->number,
            'created_at'         => $this->payment->created_at->toIso8601String(),
        ];
    }
}