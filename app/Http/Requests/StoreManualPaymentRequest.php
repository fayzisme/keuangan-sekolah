<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = auth()->user()?->activeSchool()?->id;

        return [
            'cashier_name' => ['nullable', 'string', 'max:255'],
            'proof_path' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => [
                'required', 'integer',
                Rule::exists('invoices', 'id')->where('school_id', $schoolId),
            ],
            'allocations.*.amount_cents' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $schoolId = auth()->user()?->activeSchool()?->id;
            $allocations = $this->input('allocations', []);

            // Validasi: invoice harus milik sekolah aktif, belum PAID/VOID
            foreach ($allocations as $i => $alloc) {
                $invoice = \App\Domain\Billing\Models\BillingInvoice::query()
                    ->where('school_id', $schoolId)
                    ->whereKey($alloc['invoice_id'])
                    ->first();

                if ($invoice) {
                    if (in_array($invoice->status, ['PAID', 'VOID'], true)) {
                        $validator->errors()->add("allocations.{$i}.invoice_id", "Invoice {$alloc['invoice_id']} sudah lunas/void.");
                    }
                    if ((int) $alloc['amount_cents'] > $invoice->amount_cents) {
                        $validator->errors()->add("allocations.{$i}.amount_cents", "Melebihi sisa invoice {$alloc['invoice_id']}.");
                    }
                }
            }
        });
    }
}
