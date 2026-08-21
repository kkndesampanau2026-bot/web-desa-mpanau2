<?php

namespace Database\Seeders;

use App\Models\BansosRecipient;
use App\Models\BansosType;
use App\Models\Dusun;
use App\Models\PpidInformationItem;
use App\Models\PpidLegalBasis;
use App\Models\Village;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data contoh Fase 4 — Bansos & PPID.
 *
 * PENTING: seluruh nama dan NIK penerima di bawah adalah DATA FIKTIF.
 * Data penerima bantuan sosial yang sebenarnya adalah data pribadi warga dan
 * WAJIB diinput admin desa dari dokumen resmi (SK penetapan penerima), bukan
 * disalin dari mana pun.
 *
 * Dasar hukum PPID di bawah merujuk regulasi yang benar-benar ada; nomor dan
 * tahunnya tetap perlu diverifikasi admin sebelum dipublikasikan.
 *
 * Tidak dijalankan di produksi.
 */
class BansosPpidDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('BansosPpidDemoSeeder dilewati: tidak dijalankan di produksi.');

            return;
        }

        $village = Village::where('slug', 'mpanau')->first();

        if (! $village) {
            $this->command->error('Desa Mpanau belum ada. Jalankan VillageSeeder lebih dulu.');

            return;
        }

        $this->bansos($village);
        $this->ppid($village);

        $this->command->info('Data Bansos & PPID demo Fase 4 dibuat (data fiktif).');
    }

    private function bansos(Village $village): void
    {
        $jenis = [];

        foreach ([
            ['BLT Dana Desa', 'Bantuan Langsung Tunai yang bersumber dari Dana Desa.', 'Dana Desa'],
            ['PKH', 'Program Keluarga Harapan bagi keluarga penerima manfaat.', 'APBN'],
            ['BPNT / Kartu Sembako', 'Bantuan Pangan Non Tunai untuk kebutuhan pokok.', 'APBN'],
            ['PIP', 'Program Indonesia Pintar untuk anak usia sekolah.', 'APBN'],
        ] as $urutan => [$nama, $deskripsi, $sumber]) {
            $jenis[$nama] = BansosType::updateOrCreate(
                ['village_id' => $village->id, 'slug' => Str::slug($nama)],
                [
                    'nama' => $nama,
                    'deskripsi' => $deskripsi,
                    'sumber_dana' => $sumber,
                    'status_aktif' => true,
                    'urutan_tampil' => $urutan,
                ]
            );
        }

        $dusun = Dusun::where('village_id', $village->id)->pluck('id')->all();

        // Penerima fiktif. NIK memakai awalan 9999 agar tidak menyerupai NIK
        // sungguhan siapa pun.
        $penerima = [
            ['Ahmad Fauzi', '9999010126000101', 'BLT Dana Desa', 600000],
            ['Siti Nurhaliza', '9999010126000102', 'BLT Dana Desa', 600000],
            ['Muhammad Rizki', '9999010126000103', 'PKH', 750000],
            ['Dewi Lestari', '9999010126000104', 'PKH', 750000],
            ['Bambang Sutrisno', '9999010126000105', 'BPNT / Kartu Sembako', 200000],
            ['Rina Marlina', '9999010126000106', 'BPNT / Kartu Sembako', 200000],
            ['Andi Pratama', '9999010126000107', 'PIP', 450000],
            ['Nur Aisyah', '9999010126000108', 'BLT Dana Desa', 600000],
        ];

        foreach ($penerima as $i => [$nama, $nik, $namaJenis, $nominal]) {
            BansosRecipient::updateOrCreate(
                [
                    'village_id' => $village->id,
                    'bansos_type_id' => $jenis[$namaJenis]->id,
                    'tahun_anggaran' => 2026,
                    'nik_hash' => app(\App\Services\PiiCipher::class)->hash($nik),
                ],
                [
                    'nama' => $nama,
                    'nik' => $nik,
                    'dusun_id' => $dusun ? $dusun[$i % count($dusun)] : null,
                    'status' => 'aktif',
                    'nominal' => $nominal,
                    // Nominal sengaja disembunyikan dari publik secara bawaan.
                    'nominal_publik' => false,
                ]
            );
        }
    }

    private function ppid(Village $village): void
    {
        foreach ([
            ['Undang-Undang Keterbukaan Informasi Publik', 'Nomor 14 Tahun 2008', 2008],
            ['Undang-Undang Desa', 'Nomor 6 Tahun 2014', 2014],
            ['Undang-Undang Pelindungan Data Pribadi', 'Nomor 27 Tahun 2022', 2022],
            ['Peraturan Menteri Dalam Negeri tentang Pengelolaan Keuangan Desa', 'Nomor 20 Tahun 2018', 2018],
        ] as $urutan => [$judul, $nomor, $tahun]) {
            PpidLegalBasis::updateOrCreate(
                ['village_id' => $village->id, 'judul_regulasi' => $judul],
                ['nomor_regulasi' => $nomor, 'tahun' => $tahun, 'urutan_tampil' => $urutan]
            );
        }

        $informasi = [
            ['berkala', 'Laporan Realisasi APBDes Semester I 2026', 'Laporan Keuangan', 'Semester I 2026', null],
            ['berkala', 'Laporan Penyelenggaraan Pemerintahan Desa 2025', 'Laporan Tahunan', 'Tahun 2025', null],
            ['berkala', 'Rencana Kerja Pemerintah Desa 2026', 'Perencanaan', 'Tahun 2026', null],
            ['serta-merta', 'Imbauan Kesiapsiagaan Menghadapi Musim Hujan', 'Kebencanaan', null, 'sedang'],
            ['setiap-saat', 'Peraturan Desa tentang APBDes 2026', 'Peraturan Desa', null, null],
            ['setiap-saat', 'RPJMDes Desa Mpanau 2025–2030', 'Perencanaan', null, null],
            ['setiap-saat', 'Daftar Aset dan Inventaris Desa', 'Aset Desa', null, null],
        ];

        foreach ($informasi as $i => [$jenis, $judul, $kategori, $periode, $urgensi]) {
            PpidInformationItem::updateOrCreate(
                ['village_id' => $village->id, 'jenis' => $jenis, 'judul' => $judul],
                [
                    'kategori' => $kategori,
                    'periode' => $periode,
                    'tingkat_urgensi' => $urgensi,
                    'tanggal_publish' => now()->subDays($i * 12)->toDateString(),
                    'status_tampil' => true,
                ]
            );
        }
    }
}
