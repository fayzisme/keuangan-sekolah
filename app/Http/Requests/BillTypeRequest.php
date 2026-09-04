<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BillTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = auth()->user()?->activeSchool()?->id;
        $billTypeId = $this->route('id');

        return [
            'name' => [
                'required', 'string', 'max:255',
                // Unique per school; exclude soft-deleted.
                Rule::unique('bill_types', 'name')
                    ->where(fn ($q) => $q
                        ->where('school_id', $schoolId)
                        ->whereNull('deleted_at'))
                    ->ignore($billTypeId),
            ],
            'tipe_bayar' => ['required', 'in:monthly,one_time'],
            'tarif_cents' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ];
    }
}
