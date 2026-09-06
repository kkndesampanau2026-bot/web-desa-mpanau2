<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Village;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun operator awal untuk pengembangan.
 *
 * Kata sandi diambil dari env agar kredensial tidak pernah ter-commit ke repo.
 * Seeder ini hanya boleh dijalankan di lingkungan non-produksi; di produksi
 * akun pertama dibuat manual lewat `php artisan app:create-admin`.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('AdminUserSeeder dilewati: tidak dijalankan di produksi.');

            return;
        }

        $village = Village::where('slug', 'mpanau')->first();
        $password = env('SEED_ADMIN_PASSWORD', 'password');

        $akun = [
            ['name' => 'Super Admin', 'email' => 'superadmin@desa.test', 'role' => 'Super Admin'],
            ['name' => 'Admin Desa Mpanau', 'email' => 'admin@desa.test', 'role' => 'Admin Utama'],
            ['name' => 'Operator Desa', 'email' => 'operator@desa.test', 'role' => 'Operator Utama'],
            ['name' => 'Operator Konten', 'email' => 'konten@desa.test', 'role' => 'Operator Konten'],
            ['name' => 'Operator PPID', 'email' => 'ppid@desa.test', 'role' => 'Operator PPID'],
        ];

        foreach ($akun as $data) {
            $user = User::withTrashed()->updateOrCreate(
                ['email' => $data['email']],
                [
                    // Super Admin lintas-tenant, karena itu tidak diikat ke satu desa.
                    'village_id' => $data['role'] === 'Super Admin' ? null : $village?->id,
                    'name' => $data['name'],
                    'password' => Hash::make($password),
                    'status_aktif' => true,
                    'deleted_at' => null,
                ]
            );

            $user->syncRoles([$data['role']]);
        }

        $this->command->info(count($akun).' akun operator dibuat. Kata sandi: '.$password);
    }
}
