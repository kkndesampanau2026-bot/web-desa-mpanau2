<?php

namespace Database\Seeders;

use App\Models\Potential;
use App\Models\Product;
use App\Models\TourismSpot;
use App\Models\Village;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data contoh Fase 5 — Potensi Desa, Wisata, dan Produk UMKM.
 *
 * PENTING: seluruh nama usaha, nama penjual, nomor kontak, dan destinasi di
 * bawah adalah DATA FIKTIF. Nomor WhatsApp sengaja memakai awalan 0812-0000
 * agar tidak menyerupai nomor sungguhan milik siapa pun.
 *
 * Data pelaku UMKM dan destinasi wisata yang sebenarnya wajib diinput admin
 * desa atas persetujuan pemiliknya — nomor kontak seseorang tidak boleh
 * dipublikasikan tanpa sepengetahuannya.
 *
 * Tidak dijalankan di produksi.
 */
class EkonomiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('EkonomiDemoSeeder dilewati: tidak dijalankan di produksi.');

            return;
        }

        $village = Village::where('slug', 'mpanau')->first();

        if (! $village) {
            $this->command->error('Desa Mpanau belum ada. Jalankan VillageSeeder lebih dulu.');

            return;
        }

        $this->potensi($village);
        $this->wisata($village);
        $this->produk($village);

        $this->command->info('Data Potensi, Wisata & UMKM demo Fase 5 dibuat (data fiktif).');
    }

    private function potensi(Village $village): void
    {
        $daftar = [
            [
                'Pertanian',
                'Lahan Persawahan Produktif',
                'Hamparan sawah yang menjadi sumber penghidupan utama sebagian besar '
                    .'warga desa, dengan pola tanam dua kali setahun.',
            ],
            [
                'Ekonomi',
                'Kelompok Usaha Bersama Warga',
                'Kelompok usaha yang mengolah hasil pertanian menjadi produk bernilai '
                    .'tambah seperti keripik dan gula aren.',
            ],
            [
                'Pariwisata',
                'Panorama Alam Kaki Bukit',
                'Bentang alam kaki bukit yang berpotensi dikembangkan sebagai '
                    .'destinasi wisata alam dan perkemahan.',
            ],
            [
                'Industri Kreatif',
                'Kerajinan Anyaman Bambu',
                'Keterampilan menganyam bambu yang diwariskan turun-temurun dan '
                    .'kini mulai dipasarkan ke luar desa.',
            ],
            [
                'Lingkungan/Kelestarian',
                'Pengelolaan Sampah Berbasis Dusun',
                'Inisiatif bank sampah tingkat dusun untuk memilah dan mengolah '
                    .'sampah rumah tangga.',
            ],
        ];

        foreach ($daftar as $i => [$kategori, $judul, $deskripsi]) {
            Potential::updateOrCreate(
                ['village_id' => $village->id, 'slug' => Str::slug($judul)],
                [
                    'kategori' => $kategori,
                    'judul' => $judul,
                    'deskripsi' => $deskripsi,
                    'status_tampil' => true,
                    'urutan_tampil' => $i,
                ]
            );
        }
    }

    private function wisata(Village $village): void
    {
        $jamUmum = [
            'senin' => ['buka' => '08:00', 'tutup' => '17:00', 'libur' => false],
            'selasa' => ['buka' => '08:00', 'tutup' => '17:00', 'libur' => false],
            'rabu' => ['buka' => '08:00', 'tutup' => '17:00', 'libur' => false],
            'kamis' => ['buka' => '08:00', 'tutup' => '17:00', 'libur' => false],
            'jumat' => ['buka' => '08:00', 'tutup' => '17:00', 'libur' => false],
            'sabtu' => ['buka' => '07:00', 'tutup' => '18:00', 'libur' => false],
            'minggu' => ['buka' => '07:00', 'tutup' => '18:00', 'libur' => false],
        ];

        $daftar = [
            [
                'Panorama Kaki Bukit Mpanau',
                'Titik pandang dengan pemandangan lembah dan persawahan desa, '
                    .'ramai dikunjungi menjelang matahari terbenam.',
                'Gratis',
                ['Parkir', 'Toilet', 'Warung', 'Gazebo'],
            ],
            [
                'Sungai Wisata Desa',
                'Area tepi sungai yang dimanfaatkan warga untuk rekreasi keluarga '
                    .'dan kegiatan gotong royong bersih sungai.',
                'Sukarela',
                ['Parkir', 'Toilet', 'Mushola'],
            ],
        ];

        foreach ($daftar as [$nama, $deskripsi, $tiket, $fasilitas]) {
            TourismSpot::updateOrCreate(
                ['village_id' => $village->id, 'slug' => Str::slug($nama)],
                [
                    'nama' => $nama,
                    'deskripsi' => $deskripsi,
                    'alamat' => 'Desa Mpanau, Kec. Sigi Biromaru, Kab. Sigi',
                    'harga_tiket' => $tiket,
                    'fasilitas' => $fasilitas,
                    'jam_operasional' => $jamUmum,
                    'kontak_pengelola' => 'Pokdarwis Desa Mpanau',
                    'status_tampil' => true,
                ]
            );
        }
    }

    private function produk(Village $village): void
    {
        // Nomor WhatsApp fiktif — awalan 0812-0000 tidak dialokasikan untuk
        // pelanggan sungguhan, sehingga aman dipakai sebagai contoh.
        $daftar = [
            ['Keripik Pisang Original', 'Makanan Ringan', 25000, 'bungkus', 'Ibu Sari', '081200000001'],
            ['Keripik Pisang Balado', 'Makanan Ringan', 27000, 'bungkus', 'Ibu Sari', '081200000001'],
            ['Gula Aren Cetak', 'Hasil Pertanian', 20000, 'kg', 'Pak Budi', '081200000002'],
            ['Kopi Bubuk Robusta', 'Minuman', 45000, 'pak 250 gr', 'Kelompok Tani Maju', '081200000003'],
            ['Anyaman Bambu Serbaguna', 'Kerajinan', 75000, 'buah', 'Pak Anwar', '081200000004'],
            ['Madu Hutan Murni', 'Hasil Hutan', 120000, 'botol 500 ml', 'Kelompok Tani Maju', '081200000003'],
            ['Beras Merah Lokal', 'Hasil Pertanian', 18000, 'kg', 'Gapoktan Desa', '081200000005'],
            ['Abon Ikan', 'Makanan Olahan', 35000, 'toples', 'Ibu Rina', '081200000006'],
        ];

        foreach ($daftar as $i => [$nama, $kategori, $harga, $satuan, $penjual, $wa]) {
            Product::updateOrCreate(
                ['village_id' => $village->id, 'slug' => Str::slug($nama)],
                [
                    'nama_produk' => $nama,
                    'kategori' => $kategori,
                    'deskripsi' => "Produk {$nama} hasil olahan pelaku UMKM Desa Mpanau.",
                    'harga' => $harga,
                    'satuan' => $satuan,
                    // Satu produk dibuat habis agar pengurutan "tersedia dulu"
                    // ikut terlihat pada tampilan.
                    'tersedia' => $i !== 4,
                    'nama_penjual' => $penjual,
                    'kontak_wa' => $wa,
                    'alamat_penjual' => 'Desa Mpanau, Kec. Sigi Biromaru',
                    'status_tampil' => true,
                ]
            );
        }
    }
}
