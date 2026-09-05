<?php

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillType;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\School;
use App\Domain\Student\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

// Bersihkan cache permission Spatie setiap test: teams mode menyimpan team-id di cache
// per request; tanpa ini role dari test sebelumnya bisa "bocor" menyatu (state bleed),
// membuat test lulus terisolasi tapi gagal saat berjalan berurutan di CI.
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/**
 * Helper test: buat user ber-role di sekolah dgn konteks aktif.
 */
function makeScopedUser(School $school, string $roleName = 'admin'): User
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
    Role::findOrCreate($roleName, 'sanctum');

    $user = User::create([
        'name' => ucfirst($roleName).' '.$school->name,
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
    ]);
    $user->schools()->attach($school->id, ['is_active' => true]);
    $user->assignRole($roleName);

    return $user;
}

/**
 * Helper test: seed sekolah + tahun ajaran + bill type + murid + invoice (OPEN).
 * Dipakai bersama oleh test pembayaran. Didefinisikan sekali di sini untuk
 * menghindari fatal "Cannot redeclare function" saat dua file test mendeklarasikan
 * fungsi global dengan nama sama.
 */
if (! function_exists('seededSchool')) {
    function seededSchool(string $name = 'SMA A'): array
    {
        $school = School::create(['name' => $name]);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026', 'semester' => 'ganjil']);
        $bt = BillType::create(['school_id' => $school->id, 'name' => 'SPP', 'tipe_bayar' => 'monthly', 'tarif_cents' => 300000]);
        $student = Student::create(['school_id' => $school->id, 'nis' => 'A-1', 'name' => 'Siswa A', 'is_active' => true]);
        $invoice = BillingInvoice::create([
            'school_id' => $school->id, 'student_id' => $student->id, 'bill_type_id' => $bt->id,
            'academic_year_id' => $year->id, 'periode_bulan' => 1, 'periode_tahun' => 2025,
            'amount_cents' => 300000, 'status' => 'OPEN',
        ]);

        return [$school, $year, $bt, $student, $invoice];
    }
}
