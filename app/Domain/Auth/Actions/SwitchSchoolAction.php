<?php

namespace App\Domain\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SwitchSchoolAction
{
    public function __invoke(User $user, int $schoolId): array
    {
        return DB::transaction(function () use ($user, $schoolId) {
            // Validasi keanggotaan SEBELUM mutasi apa pun — cegah akun terkunci (bricked)
            // saat school_id tidak dimiliki user.
            $membership = $user->schools()->where('schools.id', $schoolId)->first();

            if (is_null($membership)) {
                throw ValidationException::withMessages([
                    'school_id' => ['Anda tidak terdaftar di sekolah ini.'],
                ]);
            }

            // Atomic: nonaktifkan SEMUA pivot user sekaligus (bukan loop per baris),
            // mencegah race condition yang menghasilkan >1 sekolah aktif.
            DB::table('school_user')
                ->where('user_id', $user->id)
                ->update(['is_active' => false]);

            $user->schools()->updateExistingPivot($schoolId, ['is_active' => true]);

            return [
                'message' => 'Konteks sekolah berhasil diubah.',
                'active_school' => ['id' => $membership->id, 'name' => $membership->name],
            ];
        });
    }
}
