<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VoidInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return []; // Validasi status di Action.
    }
}
