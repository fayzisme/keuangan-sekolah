<?php

namespace App\Http\Requests;

use App\Domain\Student\Models\Guardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AttachGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = auth()->user()?->activeSchool()?->id;

        return [
            // Guard cuma boleh ditautkan jika:
            // (a) belum punya student sama sekali (orphan — yang lain akan ditautkan ke murid sekolah ini), ATAU
            // (b) sudah terhubung ke ≥1 student di SEKOLAH AKTIF ini (mis. ortu dengan 2 anak di sekolah yang sama).
            // Tujuannya: cegah "menculik" data pribadi guardian milik sekolah lain (name+no_hp)
            // hanya dengan menautkannya ke murid sendiri.
            'guardian_id' => [
                'required', 'integer', 'exists:guardians,id',
                function ($attribute, $value, $fail) use ($schoolId): void {
                    $allowed = Guardian::query()
                        ->whereKey($value)
                        ->where(function ($q) use ($schoolId) {
                            $q->whereDoesntHave('students')
                                ->orWhereHas('students', fn ($s) => $s->where('school_id', $schoolId));
                        })
                        ->exists();

                    if (! $allowed) {
                        $fail('Guardian ini terhubung ke murid di sekolah lain dan tidak boleh ditautkan di sini.');
                    }
                },
            ],
            'relation' => ['sometimes', 'in:ayah,ibu,wali'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}