<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = auth()->user()?->activeSchool()?->id;
        $yearId = $this->route('id');

        return [
            'name' => [
                'required', 'string', 'max:50',
                // Unique per (school, semester). Harus exclude soft-deleted: baris soft-delete
                // masih ada di DB; tanpa whereNull, create ulang nama yang sama → 500 constraint,
                // bukan 422. (Juga berlaku untuk NIS & nama kelas — lihat request terkait.)
                Rule::unique('academic_years', 'name')
                    ->where(fn ($q) => $q
                        ->where('school_id', $schoolId)
                        ->where('semester', $this->input('semester', 'ganjil'))
                        ->whereNull('deleted_at'))
                    ->ignore($yearId),
            ],
            'semester' => ['required', 'in:ganjil,genap'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ];
    }
}
