<?php

namespace Database\Seeders;

use App\Models\PointOfInterest;
use App\Models\TourismSpot;
use App\Models\Village;
use Illuminate\Database\Seeder;

/**
 * Titik lokasi contoh Fase 6.
 *
 * PENTING: seluruh koordinat di bawah adalah PERKIRAAN di sekitar wilayah
 * Sigi Biromaru, DIBUAT UNTUK KEPERLUAN TAMPILAN SAJA. Titik sesungguhnya
 * wajib diambil admin desa dari lokasi fisik yang benar — peta yang
 * menunjukkan puskesmas di tempat yang keliru lebih berbahaya daripada peta
 * yang kosong.
 *
 * Tidak dijalankan di produksi.
 */
class PetaDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('PetaDemoSeeder dilewati: tidak dijalankan di produksi.');

            return;
        }

        $village = Village::where('slug', 'mpanau')->first();

        if (! $village) {
            $this->command->error('Desa Mpanau belum ada. Jalankan VillageSeeder lebih dulu.');

            return;
        }

        // Titik acuan perkiraan wilayah Desa Mpanau.
        $lat = -0.9553;
        $lng = 119.9089;

        $daftar = [
            ['Kantor Desa Mpanau', 'Kantor Pemerintahan', 0.0000, 0.0000],
            ['Puskesmas Pembantu', 'Fasilitas Kesehatan', 0.0018, 0.0012],
            ['Posyandu Dusun 1', 'Fasilitas Kesehatan', -0.0015, 0.0020],
            ['SD Negeri Mpanau', 'Fasilitas Pendidikan', 0.0025, -0.0018],
            ['PAUD Melati', 'Fasilitas Pendidikan', -0.0022, -0.0009],
            ['Masjid Jami Desa', 'Tempat Ibadah', 0.0009, 0.0027],
            ['Balai Pertemuan Warga', 'Kantor Pemerintahan', -0.0007, -0.0024],
            ['Pasar Desa', 'UMKM/Usaha', 0.0031, 0.0008],
        ];

        foreach ($daftar as [$nama, $kategori, $dLat, $dLng]) {
            PointOfInterest::updateOrCreate(
                ['village_id' => $village->id, 'nama' => $nama],
                [
                    'kategori' => $kategori,
                    'deskripsi' => "{$nama} di Desa Mpanau.",
                    'latitude' => round($lat + $dLat, 7),
                    'longitude' => round($lng + $dLng, 7),
                    'alamat' => 'Desa Mpanau, Kec. Sigi Biromaru, Kab. Sigi',
                    'status_tampil' => true,
                ]
            );
        }

        // Destinasi wisata ikut ditandai di peta dan ditautkan ke halamannya,
        // memanfaatkan relasi opsional pada PRD 6.9.
        foreach (TourismSpot::where('village_id', $village->id)->get() as $i => $wisata) {
            PointOfInterest::updateOrCreate(
                ['village_id' => $village->id, 'nama' => $wisata->nama],
                [
                    'kategori' => 'Wisata',
                    'deskripsi' => $wisata->deskripsi,
                    'latitude' => round($lat + 0.0040 + ($i * 0.0012), 7),
                    'longitude' => round($lng - 0.0035 - ($i * 0.0010), 7),
                    'alamat' => $wisata->alamat,
                    'tourism_spot_id' => $wisata->id,
                    'status_tampil' => true,
                ]
            );
        }

        $this->command->info('Titik lokasi demo Fase 6 dibuat (koordinat perkiraan).');
    }
}
