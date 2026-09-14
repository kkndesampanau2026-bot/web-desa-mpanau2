<?php

namespace Tests\Feature\Admin;

use App\Models\Complaint;
use App\Models\News;
use App\Models\Potential;
use App\Models\Product;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Beranda dashboard CMS — PRD 5.20.
 *
 * Yang diuji terberat di sini penyaringan izinnya, bukan kecantikan angkanya.
 * Sebuah kartu "Warga Terdata — 1.204" memberi tahu Operator PPID berapa jiwa
 * yang terdata desa ini, dan "Aduan Selesai" membocorkan bahwa ada warga yang
 * mengadu — dua hal yang sengaja ditutup dari perannya (PRD 12.2). Karena
 * dashboard adalah layar PERTAMA yang dibuka setiap operator, kebocoran di
 * sini terjadi pada setiap kali masuk, tanpa perlu menekan apa pun.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Village $desa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->desa = Village::create([
            'nama' => 'Desa Uji', 'slug' => 'desa-uji', 'is_active' => true,
        ]);
    }

    private function operator(string $peran): User
    {
        $user = User::create([
            'name' => "Akun {$peran}",
            'email' => 'op'.uniqid().'@uji.test',
            'password' => Hash::make('rahasia123'),
            'village_id' => $this->desa->id,
            'status_aktif' => true,
        ]);
        $user->assignRole($peran);

        return $user;
    }

    /** @return array<string, mixed> Seluruh prop halaman dashboard. */
    private function props(User $pengguna): array
    {
        $props = [];

        $this->actingAs($pengguna)->get('/admin')->assertOk()->assertInertia(
            function (Assert $page) use (&$props) {
                $page->component('Admin/Dashboard');
                $props = $page->toArray()['props'];
            }
        );

        return $props;
    }

    /** @return array<string, int> Nilai kartu ringkasan, dipetakan per kunci. */
    private function nilaiRingkasan(User $pengguna): array
    {
        return collect($this->props($pengguna)['ringkasan'])
            ->pluck('nilai', 'kunci')
            ->all();
    }

    private function berita(string $status, ?int $villageId = null): News
    {
        return News::create([
            'village_id' => $villageId ?? $this->desa->id,
            'judul' => 'Judul Uji '.uniqid(),
            'slug' => 'judul-uji-'.uniqid(),
            'konten' => 'Isi berita uji.',
            'status' => $status,
            'tanggal_publish' => $status === 'published' ? now()->subDay() : null,
        ]);
    }

    private function pengaduan(string $status, ?string $tanggalTanggapan = null): Complaint
    {
        return Complaint::create([
            'village_id' => $this->desa->id,
            'nomor_tiket' => Complaint::buatNomorTiket(),
            'nama' => 'Warga Pelapor',
            'no_telepon_wa' => '081234567890',
            'kategori_pengaduan' => 'PEK. UMUM',
            'isi_pengaduan' => 'Jalan berlubang di depan balai desa.',
            'status' => $status,
            'tanggal_tanggapan' => $tanggalTanggapan,
        ]);
    }

    // -----------------------------------------------------------------
    // Penyaringan izin
    // -----------------------------------------------------------------

    public function test_operator_ppid_tidak_menerima_angka_modul_di_luar_kewenangannya(): void
    {
        $this->berita('published');
        $this->pengaduan('selesai', now()->toDateTimeString());

        $props = $this->props($this->operator('Operator PPID'));
        $kunci = collect($props['ringkasan'])->pluck('kunci')->all();

        $this->assertNotContains('penduduk', $kunci);
        $this->assertNotContains('pengaduan', $kunci);
        $this->assertNotContains('berita', $kunci);

        $this->assertNull(
            $props['berita_terbaru'],
            'Judul berita — termasuk draf yang belum siap dibaca siapa pun — '
            .'tidak boleh sampai ke operator tanpa `manage-news`.'
        );
    }

    public function test_operator_konten_menerima_modul_kontennya_saja(): void
    {
        $kunci = collect($this->props($this->operator('Operator Konten'))['ringkasan'])
            ->pluck('kunci')->all();

        $this->assertContains('berita', $kunci);
        $this->assertContains('potensi', $kunci);
        $this->assertContains('pengaduan', $kunci);

        // Operator Konten tidak memegang `manage-population-data`.
        $this->assertNotContains('penduduk', $kunci);
    }

    public function test_admin_utama_menerima_seluruh_kartu(): void
    {
        $kunci = collect($this->props($this->operator('Admin Utama'))['ringkasan'])
            ->pluck('kunci')->all();

        foreach (['penduduk', 'berita', 'potensi', 'produk', 'peta', 'surat', 'pengaduan'] as $diharapkan) {
            $this->assertContains($diharapkan, $kunci);
        }
    }

    public function test_potensi_dan_produk_dihitung_terpisah_mengikuti_izinnya(): void
    {
        Potential::create([
            'village_id' => $this->desa->id, 'kategori' => 'Pertanian',
            'judul' => 'Sawah', 'slug' => 'sawah', 'deskripsi' => 'Uji',
            'status_tampil' => true,
        ]);
        Product::create([
            'village_id' => $this->desa->id, 'nama_produk' => 'Keripik',
            'slug' => 'keripik', 'kategori' => 'Makanan', 'deskripsi' => 'Uji',
            'nama_penjual' => 'Ibu Uji', 'status_tampil' => true,
        ]);

        $nilai = $this->nilaiRingkasan($this->operator('Admin Utama'));

        // Kalau keduanya dijumlahkan menjadi satu kartu, operator yang hanya
        // memegang salah satu izin tetap membaca jumlah yang lain.
        $this->assertSame(1, $nilai['potensi']);
        $this->assertSame(1, $nilai['produk']);
    }

    // -----------------------------------------------------------------
    // Kebenaran angka
    // -----------------------------------------------------------------

    public function test_berita_draf_tidak_terhitung_tayang_tetapi_tetap_diingatkan(): void
    {
        $this->berita('published');
        $this->berita('draft');
        $this->berita('draft');

        $kartu = collect($this->props($this->operator('Admin Utama'))['ringkasan'])
            ->firstWhere('kunci', 'berita');

        $this->assertSame(1, $kartu['nilai']);
        $this->assertSame('2 masih draf', $kartu['keterangan']);
    }

    public function test_aduan_selesai_dihitung_dari_waktu_tanggapan_bukan_waktu_sunting(): void
    {
        // Ditanggapi bulan lalu, lalu tanggapannya diperbaiki hari ini —
        // `updated_at` menjadi hari ini, tetapi penyelesaiannya bukan bulan ini.
        $lama = $this->pengaduan('selesai', now()->subMonth()->toDateTimeString());
        $lama->touch();

        $this->pengaduan('selesai', now()->toDateTimeString());

        $nilai = $this->nilaiRingkasan($this->operator('Admin Utama'));

        $this->assertSame(1, $nilai['pengaduan']);
    }

    public function test_data_desa_lain_tidak_ikut_terhitung(): void
    {
        $desaLain = Village::create([
            'nama' => 'Desa Lain', 'slug' => 'desa-lain', 'is_active' => true,
        ]);

        $this->berita('published');
        $this->berita('published', $desaLain->id);

        $nilai = $this->nilaiRingkasan($this->operator('Admin Utama'));

        $this->assertSame(1, $nilai['berita']);
    }

    public function test_berita_terbaru_memuat_draf_dan_dibatasi_lima(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->berita('draft');
        }

        $terbaru = $this->props($this->operator('Admin Utama'))['berita_terbaru'];

        $this->assertCount(5, $terbaru);
        $this->assertSame(
            'draft',
            $terbaru[0]['status'],
            'Justru yang belum terbit yang paling mudah terlupakan, jadi draf ikut tampil.'
        );
    }

    // -----------------------------------------------------------------
    // Kunjungan
    // -----------------------------------------------------------------

    public function test_ringkasan_kunjungan_selalu_dikirim(): void
    {
        // Operator PPID pun menerimanya: jumlah kunjungan halaman publik bukan
        // data pribadi siapa pun dan tidak terikat kewenangan modul.
        $kunjungan = $this->props($this->operator('Operator PPID'))['kunjungan'];

        foreach (['hari_ini', 'kemarin', 'minggu_ini', 'minggu_lalu', 'bulan_ini', 'bulan_lalu', 'total'] as $kolom) {
            $this->assertArrayHasKey($kolom, $kunjungan);
        }
    }
}
