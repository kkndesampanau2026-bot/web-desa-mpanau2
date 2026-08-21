<?php

namespace Database\Seeders;

use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetYear;
use App\Models\Dusun;
use App\Models\IdmScore;
use App\Models\Resident;
use App\Models\SdgsScore;
use App\Models\StuntingRecord;
use App\Models\Village;
use App\Services\PopulationAggregator;
use Illuminate\Database\Seeder;

/**
 * Data contoh Fase 3 untuk pengembangan & demo.
 *
 * PENTING: seluruh angka di bawah adalah DATA FIKTIF. Data kependudukan,
 * APBDes, stunting, IDM, dan SDGs yang sebenarnya WAJIB diinput admin desa
 * dari dokumen resmi — angka pada modul transparansi dibaca publik sebagai
 * pernyataan resmi desa, sehingga tidak boleh berisi karangan.
 *
 * Tidak dijalankan di produksi.
 */
class DataTransparansiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('DataTransparansiDemoSeeder dilewati: tidak dijalankan di produksi.');

            return;
        }

        $village = Village::where('slug', 'mpanau')->first();

        if (! $village) {
            $this->command->error('Desa Mpanau belum ada. Jalankan VillageSeeder lebih dulu.');

            return;
        }

        $this->penduduk($village);
        $this->apbdes($village);
        $this->stunting($village);
        $this->idm($village);
        $this->sdgs($village);

        $this->command->info('Data transparansi demo Fase 3 dibuat (data fiktif).');
    }

    /** Membuat contoh penduduk agar infografis memiliki isi. */
    private function penduduk(Village $village): void
    {
        $dusun = Dusun::where('village_id', $village->id)->pluck('id')->all();

        if ($dusun === []) {
            return;
        }

        $pendidikan = [
            'Tidak/Belum Sekolah', 'Tamat SD/Sederajat', 'SLTP/Sederajat',
            'SLTA/Sederajat', 'Diploma IV/Strata I',
        ];
        $pekerjaan = ['Petani', 'Pedagang', 'Wiraswasta', 'Pegawai Negeri Sipil', 'Pelajar/Mahasiswa', 'Mengurus Rumah Tangga'];
        $agama = ['Islam', 'Kristen', 'Katolik', 'Hindu'];

        // 120 baris cukup untuk membuat setiap grafik terisi tanpa membuat
        // seeder berjalan lama.
        for ($i = 1; $i <= 120; $i++) {
            $nik = '7210010126'.str_pad((string) $i, 6, '0', STR_PAD_LEFT);
            $usia = [3, 8, 15, 25, 35, 45, 55, 65, 72][$i % 9];

            Resident::updateOrCreate(
                ['village_id' => $village->id, 'nik_hash' => app(\App\Services\PiiCipher::class)->hash($nik)],
                [
                    'nik' => $nik,
                    // Satu No. KK dipakai bertiga agar jumlah KK berbeda dari
                    // jumlah jiwa — mencerminkan keadaan sesungguhnya.
                    'no_kk' => '7210019926'.str_pad((string) intdiv($i - 1, 3), 6, '0', STR_PAD_LEFT),
                    'nama' => "Warga Contoh {$i}",
                    'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                    'tanggal_lahir' => now()->subYears($usia)->subDays($i)->toDateString(),
                    'dusun_id' => $dusun[$i % count($dusun)],
                    'status_hubungan_kk' => $i % 3 === 1 ? 'Kepala Keluarga' : ($i % 3 === 2 ? 'Istri' : 'Anak'),
                    'pendidikan_terakhir' => $usia < 7 ? 'Tidak/Belum Sekolah' : $pendidikan[$i % count($pendidikan)],
                    'pekerjaan' => $usia < 17 ? 'Pelajar/Mahasiswa' : $pekerjaan[$i % count($pekerjaan)],
                    'status_perkawinan' => $usia >= 25 ? 'Kawin' : 'Belum Kawin',
                    'agama' => $agama[$i % count($agama)],
                    'status_domisili' => 'Penduduk Tetap',
                ]
            );
        }

        app(PopulationAggregator::class)->buatSnapshot($village->id);
    }

    private function apbdes(Village $village): void
    {
        $kategori = [];

        foreach ([
            ['Pendapatan', 'Pendapatan Asli Desa'],
            ['Pendapatan', 'Pendapatan Transfer'],
            ['Belanja', 'Penyelenggaraan Pemerintahan Desa'],
            ['Belanja', 'Pelaksanaan Pembangunan Desa'],
            ['Belanja', 'Pembinaan Kemasyarakatan'],
            ['Belanja', 'Pemberdayaan Masyarakat'],
            ['Pembiayaan-Penerimaan', 'Penerimaan Pembiayaan'],
        ] as $urutan => [$kelompok, $nama]) {
            $kategori[$nama] = BudgetCategory::updateOrCreate(
                ['village_id' => $village->id, 'kelompok' => $kelompok, 'nama' => $nama],
                ['urutan_tampil' => $urutan]
            );
        }

        // Dua tahun anggaran agar grafik tren (PRD 6.4) punya cukup titik data.
        $rencana = [
            2025 => [
                'Pendapatan Asli Desa' => 45_000_000,
                'Pendapatan Transfer' => 1_150_000_000,
                'Penyelenggaraan Pemerintahan Desa' => 420_000_000,
                'Pelaksanaan Pembangunan Desa' => 520_000_000,
                'Pembinaan Kemasyarakatan' => 95_000_000,
                'Pemberdayaan Masyarakat' => 110_000_000,
            ],
            2026 => [
                'Pendapatan Asli Desa' => 62_000_000,
                'Pendapatan Transfer' => 1_240_000_000,
                'Penyelenggaraan Pemerintahan Desa' => 445_000_000,
                'Pelaksanaan Pembangunan Desa' => 610_000_000,
                'Pembinaan Kemasyarakatan' => 105_000_000,
                'Pemberdayaan Masyarakat' => 125_000_000,
            ],
        ];

        foreach ($rencana as $tahun => $items) {
            $tahunAnggaran = BudgetYear::updateOrCreate(
                ['village_id' => $village->id, 'tahun' => $tahun],
                ['status' => $tahun === 2026 ? 'berjalan' : 'ditutup', 'publikasikan' => true]
            );

            foreach ($items as $nama => $jumlah) {
                BudgetItem::updateOrCreate(
                    [
                        'village_id' => $village->id,
                        'budget_year_id' => $tahunAnggaran->id,
                        'budget_category_id' => $kategori[$nama]->id,
                        'nama_item' => $nama,
                    ],
                    [
                        'jumlah_anggaran' => $jumlah,
                        'jumlah_realisasi' => $tahun === 2025 ? round($jumlah * 0.94) : null,
                    ]
                );
            }
        }
    }

    private function stunting(Village $village): void
    {
        foreach (['2025-01' => [180, 27], '2025-07' => [186, 22], '2026-01' => [192, 18]] as $periode => [$diukur, $kasus]) {
            StuntingRecord::updateOrCreate(
                ['village_id' => $village->id, 'periode' => $periode, 'dusun_id' => null],
                ['jumlah_balita_diukur' => $diukur, 'jumlah_kasus_stunting' => $kasus]
            );
        }
    }

    private function idm(Village $village): void
    {
        $skor = IdmScore::updateOrCreate(
            ['village_id' => $village->id, 'tahun' => 2026],
            [
                'skor_iks' => 0.7543,
                'skor_ike' => 0.6167,
                'skor_ikl' => 0.8000,
                'status_idm' => 'Berkembang',
                'target_status' => 'Maju',
                'skor_minimal_target' => 0.7072,
                'publikasikan' => true,
            ]
        );

        $skor->skor_idm = $skor->hitungKomposit();
        $skor->penambahan_skor_dibutuhkan = round(
            max(0, (float) $skor->skor_minimal_target - (float) $skor->skor_idm), 4
        );
        $skor->save();

        $skor->indicators()->delete();

        foreach ([
            ['Ketersediaan Pelayanan Kesehatan', 0.8000, 'Menambah jadwal layanan Pustu', 0.05, ['Desa', 'Kabupaten']],
            ['Akses Pendidikan Dasar', 0.7500, 'Pendampingan belajar anak putus sekolah', 0.04, ['Desa']],
            ['Keberadaan BUMDes Aktif', 0.5000, 'Revitalisasi unit usaha BUMDes', 0.08, ['Desa', 'CSR']],
            ['Pengelolaan Sampah Desa', 0.6000, 'Pengadaan bank sampah dusun', 0.06, ['Desa', 'Kabupaten']],
        ] as $no => [$nama, $nilai, $kegiatan, $tambah, $pelaksana]) {
            $skor->indicators()->create([
                'no' => $no + 1,
                'nama_indikator' => $nama,
                'skor' => $nilai,
                'kegiatan_rekomendasi' => $kegiatan,
                'nilai_tambah' => $tambah,
                'pihak_pelaksana' => $pelaksana,
            ]);
        }
    }

    private function sdgs(Village $village): void
    {
        // 18 tujuan SDGs Desa versi Kemendes PDTT — tujuan ke-18 adalah
        // tambahan khusus desa di luar 17 SDGs global.
        $tujuan = [
            'Desa Tanpa Kemiskinan',
            'Desa Tanpa Kelaparan',
            'Desa Sehat dan Sejahtera',
            'Pendidikan Desa Berkualitas',
            'Keterlibatan Perempuan Desa',
            'Desa Layak Air Bersih dan Sanitasi',
            'Desa Berenergi Bersih dan Terbarukan',
            'Pertumbuhan Ekonomi Desa Merata',
            'Infrastruktur dan Inovasi Desa Sesuai Kebutuhan',
            'Desa Tanpa Kesenjangan',
            'Kawasan Permukiman Desa Aman dan Nyaman',
            'Konsumsi dan Produksi Desa Sadar Lingkungan',
            'Desa Tanggap Perubahan Iklim',
            'Desa Peduli Lingkungan Laut',
            'Desa Peduli Lingkungan Darat',
            'Desa Damai Berkeadilan',
            'Kemitraan untuk Pembangunan Desa',
            'Kelembagaan Desa Dinamis dan Budaya Desa Adaptif',
        ];

        foreach ($tujuan as $i => $nama) {
            SdgsScore::updateOrCreate(
                ['village_id' => $village->id, 'tahun' => 2026, 'goal_number' => $i + 1],
                [
                    'nama_goal' => $nama,
                    'skor' => 45 + (($i * 7) % 45),   // sebaran skor 45–89
                    'publikasikan' => true,
                ]
            );
        }
    }
}
