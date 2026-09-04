<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = auth()->user()?->activeSchool()?->id;
        $classId = $this->route('id');

        $academicYearId = $this->input('academic_year_id');

        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'name' => [
                'required', 'string', 'max:100',
                // Unique per (school, academic_year), exclude soft-deleted.
                Rule::unique('classes', 'name')
                    ->where(fn ($q) => $q
                        ->where('school_id', $schoolId)
                        ->where('academic_year_id', $academicYearId)
                        ->whereNull('deleted_at'))
                    ->ignore($classId),
            ],
            'level' => ['nullable', 'integer', 'between:1,12'],
        ];
    }
}
