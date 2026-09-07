<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Aanmaken van een schoolgebruiker (admin-only).
 * Validatie gebeurt op school-niveau: rollen worden gescoped naar de actieve school.
 */
final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Normalisatie email: DB slaat email lowercase op (zie OnboardSchoolAction),
        // dus ook hier normaliseren zodat unique-check case-insensitief werkt.
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['required', 'array', 'min:1', 'distinct'],
            'roles.*' => ['string', 'in:admin,bendahara,murid,ortua'],
        ];
    }
}
