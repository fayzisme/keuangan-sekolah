<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = auth()->user()?->activeSchool()?->id;
        $studentId = $this->route('id');

        return [
            'class_id' => ['nullable', 'integer', Rule::exists('classes', 'id')->where('school_id', $schoolId)],
            'nis' => [
                'required', 'string', 'max:30',
                // Unique per sekolah; exclude soft-deleted agar NIS bisa dipakai lagi
                // setelah siswa keluar (soft delete) tanpa 500 constraint.
                Rule::unique('students', 'nis')
                    ->where(fn ($q) => $q
                        ->where('school_id', $schoolId)
                        ->whereNull('deleted_at'))
                    ->ignore($studentId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:L,P'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'is_active' => ['boolean'],
        ];
    }
}
