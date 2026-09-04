<?php

namespace App\Domain\School\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class OnboardSchoolAction
{
    /**
     * Buat sekolah baru + user admin + pivot aktif + role admin di team sekolah.
     * Satu transaksi: gagal sebagian = rollback semua.
     *
     * @param  array{name:string, admin_name:string, admin_email:string, admin_password:string}  $data
     * @return array{school: mixed, user: User}
     */
    public function __invoke(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $school = \App\Domain\School\Models\School::create([
                'name' => $data['name'],
            ]);

            $user = User::create([
                'name' => $data['admin_name'],
                'email' => strtolower(trim($data['admin_email'])),
                'password' => Hash::make($data['admin_password']),
            ]);

            $user->schools()->attach($school->id, ['is_active' => true]);

            // Role admin di-scope ke team sekolah baru ini.
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
            Role::findOrCreate('admin', 'sanctum');
            $user->assignRole('admin');

            return [
                'school' => $school,
                'user' => $user,
            ];
        });
    }
}
