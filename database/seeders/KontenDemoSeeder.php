<?php

namespace Database\Seeders;

use App\Models\BpdMember;
use App\Models\Gallery;
use App\Models\ImportantPhoneNumber;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Official;
use App\Models\PpidInformationItem;
use App\Models\Setting;
use App\Models\SocialMediaLink;
use App\Models\User;
use App\Models\Village;
use App\Models\VillageProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Konten contoh untuk pengembangan & demo — Fase 2.
 *
 * PENTING: seluruh nama orang, nomor telepon, dan isi berita di bawah adalah
 * DATA FIKTIF, dibuat semata agar tampilan dapat diuji. Data sesungguhnya
 * wajib diinput oleh admin desa melalui dashboard.
 *
 * Tidak dijalankan di produksi.
 */
class KontenDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('KontenDemoSeeder dilewati: tidak dijalankan di produksi.');

            return;
        }

        $village = Village::where('slug', 'mpanau')->first();

        if (! $village) {
            $this->command->error('Desa Mpanau belum ada. Jalankan VillageSeeder lebih dulu.');

            return;
        }

        $this->profil($village);
        $this->sotk($village);
        $this->pengaturan($village);
        $this->berita($village);
        $this->galeri($village);
        $this->ppid($village);

        $this->command->info('Konten demo Fase 2 dibuat (data fiktif).');
    }

    private function profil(Village $village): void
    {
        VillageProfile::updateOrCreate(
            ['village_id' => $village->id],
            [
                'nama_kepala_desa' => 'Nama Kepala Desa',
                'sambutan' => '<p>Selamat datang di website resmi Desa Mpanau. Melalui '
                    .'kanal ini kami berupaya menghadirkan informasi desa secara '
                    .'terbuka dan mudah diakses oleh seluruh warga.</p>',
                'sejarah' => '<p>Riwayat berdirinya Desa Mpanau akan dilengkapi oleh '
                    .'admin desa melalui dashboard.</p>',
                'visi' => 'Terwujudnya Desa Mpanau yang mandiri, sejahtera, dan berbudaya.',
                'misi' => [
                    'Meningkatkan kualitas pelayanan publik kepada masyarakat desa.',
                    'Mengembangkan potensi ekonomi dan UMKM desa.',
                    'Memperkuat transparansi pengelolaan keuangan desa.',
                    'Melestarikan lingkungan dan budaya masyarakat setempat.',
                ],
                'luas_desa_m2' => 4_250_000,
                'batas_utara' => 'Desa Sidera',
                'batas_timur' => 'Desa Ngatabaru',
                'batas_selatan' => 'Desa Loru',
                'batas_barat' => 'Sungai Palu',
                // Koordinat perkiraan wilayah Sigi Biromaru — WAJIB dikoreksi
                // admin desa dengan titik kantor desa yang sebenarnya.
                'latitude' => -0.9553000,
                'longitude' => 119.9089000,
            ]
        );
    }

    private function sotk(Village $village): void
    {
        // Tingkat menyusun bagan: 0 Kepala Desa, 1 Sekretaris, 2 para Kaur/Kasi.
        $aparat = [
            ['Nama Kepala Desa', 'Kepala Desa', 0],
            ['Nama Sekretaris', 'Sekretaris Desa', 1],
            ['Nama Kaur Keuangan', 'Kaur Keuangan', 2],
            ['Nama Kaur Perencanaan', 'Kaur Perencanaan', 2],
            ['Nama Kasi Pemerintahan', 'Kasi Pemerintahan', 2],
            ['Nama Kasi Kesejahteraan', 'Kasi Kesejahteraan', 2],
        ];

        foreach ($aparat as $i => [$nama, $jabatan, $tingkat]) {
            Official::updateOrCreate(
                ['village_id' => $village->id, 'nama' => $nama],
                [
                    'jabatan' => $jabatan,
                    'urutan_tampil' => $i + 1,
                    'tingkat' => $tingkat,
                    'status_aktif' => true,
                ]
            );
        }

        $bpd = [
            ['Nama Ketua BPD', 'Ketua'],
            ['Nama Wakil Ketua BPD', 'Wakil Ketua'],
            ['Nama Sekretaris BPD', 'Sekretaris'],
            ['Nama Anggota BPD', 'Anggota'],
        ];

        foreach ($bpd as $i => [$nama, $jabatan]) {
            BpdMember::updateOrCreate(
                ['village_id' => $village->id, 'nama' => $nama],
                ['jabatan' => $jabatan, 'urutan_tampil' => $i + 1, 'status_aktif' => true]
            );
        }
    }

    private function pengaturan(Village $village): void
    {
        Setting::updateOrCreate(
            ['village_id' => $village->id],
            [
                'nama_desa' => 'Desa Mpanau',
                'kode_wilayah' => '72.10.01.2013',
                'kecamatan' => 'Sigi Biromaru',
                'kabupaten' => 'Sigi',
                'provinsi' => 'Sulawesi Tengah',
                'alamat_kantor' => 'Kantor Desa Mpanau, Kec. Sigi Biromaru, Kab. Sigi',
                'jam_kerja' => [
                    'senin' => ['buka' => '08:00', 'tutup' => '16:00', 'libur' => false],
                    'selasa' => ['buka' => '08:00', 'tutup' => '16:00', 'libur' => false],
                    'rabu' => ['buka' => '08:00', 'tutup' => '16:00', 'libur' => false],
                    'kamis' => ['buka' => '08:00', 'tutup' => '16:00', 'libur' => false],
                    'jumat' => ['buka' => '08:00', 'tutup' => '11:00', 'libur' => false],
                    'sabtu' => ['libur' => true],
                    'minggu' => ['libur' => true],
                ],
                'email' => 'desampanau@example.id',
            ]
        );

        $layanan = [
            ['Puskesmas Biromaru', '(0451) 000000'],
            ['Polsek Sigi Biromaru', '110'],
            ['Pemadam Kebakaran', '113'],
            ['Ambulans', '118'],
        ];

        foreach ($layanan as $i => [$nama, $nomor]) {
            ImportantPhoneNumber::updateOrCreate(
                ['village_id' => $village->id, 'nama_layanan' => $nama],
                ['nomor' => $nomor, 'urutan_tampil' => $i]
            );
        }

        SocialMediaLink::updateOrCreate(
            ['village_id' => $village->id, 'platform' => 'Facebook'],
            ['url' => 'https://facebook.com/', 'urutan_tampil' => 0]
        );
    }

    private function berita(Village $village): void
    {
        $penulis = User::where('email', 'admin@desa.test')->first();

        $kategori = collect(['Pengumuman', 'Pembangunan', 'Kegiatan Desa'])
            ->mapWithKeys(fn ($nama) => [
                $nama => NewsCategory::updateOrCreate(
                    ['village_id' => $village->id, 'slug' => Str::slug($nama)],
                    ['nama' => $nama]
                ),
            ]);

        $artikel = [
            [
                'Musyawarah Desa Penetapan APBDes 2026',
                'Pengumuman',
                'Pemerintah Desa Mpanau menyelenggarakan musyawarah desa untuk '
                    .'menetapkan Anggaran Pendapatan dan Belanja Desa tahun 2026.',
                0,
            ],
            [
                'Pembangunan Jalan Usaha Tani Tahap Pertama',
                'Pembangunan',
                'Pengerjaan jalan usaha tani sepanjang 800 meter mulai '
                    .'dilaksanakan untuk memudahkan akses petani menuju lahan.',
                4,
            ],
            [
                'Posyandu Balita dan Pemantauan Gizi Bulanan',
                'Kegiatan Desa',
                'Kegiatan posyandu rutin digelar di setiap dusun sebagai bagian '
                    .'dari upaya pencegahan stunting di Desa Mpanau.',
                9,
            ],
        ];

        foreach ($artikel as [$judul, $namaKategori, $ringkasan, $hariLalu]) {
            News::updateOrCreate(
                ['village_id' => $village->id, 'slug' => Str::slug($judul)],
                [
                    'news_category_id' => $kategori[$namaKategori]->id,
                    'penulis_id' => $penulis?->id,
                    'judul' => $judul,
                    'ringkasan' => $ringkasan,
                    'konten' => "<p>{$ringkasan}</p><p>Isi lengkap berita ini akan "
                        .'dilengkapi oleh admin desa melalui dashboard.</p>',
                    'status' => 'published',
                    'tanggal_publish' => now()->subDays($hariLalu),
                ]
            );
        }
    }

    private function galeri(Village $village): void
    {
        foreach ([
            ['Musyawarah Desa 2026', 12],
            ['Gotong Royong Warga', 30],
        ] as [$nama, $hariLalu]) {
            Gallery::updateOrCreate(
                ['village_id' => $village->id, 'slug' => Str::slug($nama)],
                [
                    'nama_album' => $nama,
                    'tanggal_kegiatan' => now()->subDays($hariLalu)->toDateString(),
                    'deskripsi' => 'Dokumentasi kegiatan '.$nama.'.',
                    'status_tampil' => true,
                ]
            );
        }
    }

    /**
     * Dokumen informasi publik PPID, dikelompokkan per kategori sesuai kategori
     * baku UU Keterbukaan Informasi Publik. Satu berkas contoh (`demo.pdf`)
     * disalin ke storage agar tautan unduh dapat diuji; sisanya bertaut ke
     * berkas yang sama demi kesederhanaan demo.
     */
    private function ppid(Village $village): void
    {
        $demo = $this->siapkanBerkasDemo();

        $dokumen = [
            // [jenis, kategori, judul, deskripsi, periode, hariLalu]
            ['berkala', 'Anggaran Pendapatan dan Belanja Desa', 'APBDes Tahun Anggaran 2025',
                'Dokumen rincian Anggaran Pendapatan dan Belanja Desa Mpanau Tahun Anggaran 2025.', 'TA 2025', 40],
            ['berkala', 'Ringkasan Laporan Keuangan', 'Laporan Realisasi APBDes Semester I 2025',
                'Ringkasan realisasi pendapatan dan belanja desa hingga semester pertama 2025.', 'Semester I 2025', 20],
            ['berkala', 'Struktur Organisasi Pemerintah Desa', 'Bagan Susunan Organisasi dan Tata Kerja 2025',
                'Bagan SOTK Pemerintah Desa Mpanau beserta nama pejabat pengisi.', null, 60],
            ['serta-merta', 'Keadaan Darurat', 'Imbauan Kesiapsiagaan Bencana Musim Hujan',
                'Informasi darurat terkait potensi bencana banjir dan longsor pada musim hujan.', null, 5],
            ['setiap-saat', 'Profil Desa', 'Profil Desa Mpanau 2025',
                'Dokumen profil desa memuat sejarah, visi-misi, dan data geografis.', null, 90],
            ['setiap-saat', 'Daftar Informasi Publik', 'Daftar Informasi Publik (DIP) Desa Mpanau',
                'Daftar seluruh informasi publik yang dikuasai Pemerintah Desa Mpanau.', null, 15],
        ];

        foreach ($dokumen as [$jenis, $kategori, $judul, $deskripsi, $periode, $hariLalu]) {
            PpidInformationItem::updateOrCreate(
                ['village_id' => $village->id, 'jenis' => $jenis, 'judul' => $judul],
                [
                    'deskripsi' => $deskripsi,
                    'kategori' => $kategori,
                    'periode' => $periode,
                    'file' => $demo,
                    'tanggal_publish' => now()->subDays($hariLalu)->toDateString(),
                    'status_tampil' => true,
                ]
            );
        }
    }

    /** Menyalin berkas PDF contoh ke storage publik; mengembalikan path relatifnya. */
    private function siapkanBerkasDemo(): string
    {
        $path = 'ppid/demo.pdf';
        $tujuan = storage_path('app/public/'.$path);

        if (! file_exists($tujuan)) {
            @mkdir(dirname($tujuan), 0755, true);
            // PDF minimal yang sah agar peramban dapat membukanya.
            file_put_contents(
                $tujuan,
                "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
                ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
                ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 144]>>endobj\n"
                ."trailer<</Root 1 0 R>>\n%%EOF"
            );
        }

        return $path;
    }
}
