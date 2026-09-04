<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Normalisasi email sebelum validasi: DB menyimpan email lowercase
        // (lihat OnboardSchoolAction), dan LoginAction mencocokkan exact match.
        // Tanpa ini, user yang mengetik 'Admin@X.com' beda kapital akan gagal login.
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}