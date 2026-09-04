<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return []; // Validasi bisnis di Action (maker-checker, status, lockForUpdate).
    }
}
