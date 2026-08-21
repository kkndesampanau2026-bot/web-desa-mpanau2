<?php

namespace Database\Seeders;

use App\Models\Dusun;
use App\Models\Village;
use Illuminate\Database\Seeder;

/**
 * Data tenant awal — Desa Mpanau, Kec. Sigi Biromaru, Kab. Sigi, Sulawesi Tengah.
 *
 * Kode wilayah mengikuti format Kemendagri xx.xx.xx.xxxx sebagaimana dicatat
 * pada PRD 3.2.
 *
 * Nama dusun di bawah ini masih PLACEHOLDER dan wajib diverifikasi ke data
 * resmi desa sebelum dipakai — PRD tidak memuat daftar dusun Desa Mpanau.
 */
class VillageSeeder extends Seeder
{
    public function run(): void
    {
        $village = Village::updateOrCreate(
            ['slug' => 'mpanau'],
            [
                'nama' => 'Desa Mpanau',
                'kode_wilayah' => '72.10.01.2013',
                'subdomain' => 'mpanau',
                'is_active' => true,
            ]
        );

        foreach (['Dusun 1', 'Dusun 2', 'Dusun 3'] as $i => $nama) {
            Dusun::updateOrCreate(
                ['village_id' => $village->id, 'nama' => $nama],
                ['urutan_tampil' => $i + 1]
            );
        }
    }
}
