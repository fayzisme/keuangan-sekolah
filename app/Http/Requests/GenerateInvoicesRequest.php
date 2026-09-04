<?php

namespace App\Http\Requests;

use App\Domain\Billing\Models\BillType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GenerateInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = auth()->user()?->activeSchool()?->id;

        return [
            'bill_type_id' => [
                'required', 'integer',
                Rule::exists('bill_types', 'id')->where('school_id', $schoolId)->where('is_active', true),
            ],
            'academic_year_id' => [
                'required', 'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $schoolId),
            ],
            'periode_bulan' => ['nullable', 'integer', 'between:1,12'],
            'periode_tahun' => ['required', 'integer', 'between:2000,2100'],
            'due_at' => ['nullable', 'date'],
            'student_ids' => ['sometimes', 'array'],
            'student_ids.*' => [
                'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $schoolId = auth()->user()?->activeSchool()?->id;
            $billTypeId = $this->input('bill_type_id');

            $tipe = BillType::query()
                ->where('school_id', $schoolId)
                ->whereKey($billTypeId)
                ->value('tipe_bayar');

            if ($tipe === 'monthly' && empty($this->input('periode_bulan'))) {
                $validator->errors()->add('periode_bulan', 'Wajib diisi untuk tagihan bulanan.');
            }

            if ($tipe === 'one_time' && $this->has('periode_bulan')) {
                $validator->errors()->add('periode_bulan', 'Tidak boleh diisi untuk tagihan satu-kali (one_time).');
            }
        });
    }
}
