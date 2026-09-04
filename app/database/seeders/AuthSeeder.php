<?php

namespace Database\Seeders;

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class AuthSeeder extends Seeder
{
    public function run(): void
    {
        // Keamanan: akun demo dengan kredensial statis hanya boleh dibuat di lingkungan non-produksi.
        if (App::environment('production')) {
            $this->command?->warn('AuthSeeder dilewati di lingkungan production.');

            return;
        }

        $school = School::create(['name' => 'SMA Merdeka Demo']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

        $roles = ['admin', 'bendahara', 'murid', 'ortua'];
        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName, 'sanctum');
        }

        $users = [
            ['name' => 'Admin Demo', 'email' => 'admin@demo.sch.id', 'role' => 'admin'],
            ['name' => 'Bendahara Demo', 'email' => 'bendahara@demo.sch.id', 'role' => 'bendahara'],
            ['name' => 'Murid Demo', 'email' => 'murid@demo.sch.id', 'role' => 'murid'],
            ['name' => 'Ortua Demo', 'email' => 'ortua@demo.sch.id', 'role' => 'ortua'],
        ];

        foreach ($users as $u) {
            $user = User::create([
                'name' => $u['name'],
                'email' => $u['email'],
                'password' => Hash::make('password123'),
            ]);

            $user->schools()->attach($school->id, ['is_active' => true]);
            $user->assignRole($u['role']);
        }
    }
}
