<?php

namespace Tests\Feature\Publik;

use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Setiap alamat pada peta situs benar-benar dilayani.
 *
 * Ditulis setelah sebagian besar halaman sempat berbalas 404: komponen React-nya
 * sudah ada, tetapi route webnya belum didaftarkan sehingga tidak ada yang
 * menyajikannya. Kegagalan semacam itu tidak terlihat oleh pengujian per modul
 * — masing-masing modul lulus, hanya alamatnya yang tidak terhubung. Karena itu
 * daftar di bawah menguji peta situs sebagai satu kesatuan.
 */
class RuteHalamanTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->village = Village::create([
            'nama' => 'Desa Uji',
            'slug' => 'desa-uji',
            'is_active' => true,
        ]);
    }

    /**
     * Seluruh halaman publik, beserta komponen Inertia yang seharusnya tampil.
     *
     * @return array<string, array{string, string}>
     */
    public static function halamanPublik(): array
    {
        return [
            'beranda' => ['/', 'Publik/Beranda'],
            'profil' => ['/profil', 'Publik/Profil'],
            'pemerintah' => ['/pemerintah', 'Publik/Pemerintah'],
            'berita' => ['/berita', 'Publik/Berita/Index'],
            'galeri' => ['/galeri', 'Publik/Galeri/Index'],
            'infografis penduduk' => ['/infografis/penduduk', 'Publik/Infografis/Penduduk'],
            'infografis apbdes' => ['/infografis/apbdes', 'Publik/Infografis/Apbdes'],
            'infografis stunting' => ['/infografis/stunting', 'Publik/Infografis/Stunting'],
            'infografis bansos' => ['/infografis/bansos', 'Publik/Infografis/Bansos'],
            'infografis idm' => ['/infografis/idm', 'Publik/Infografis/Idm'],
            'infografis sdgs' => ['/infografis/sdgs', 'Publik/Infografis/Sdgs'],
            'potensi' => ['/potensi', 'Publik/Ekonomi/Potensi'],
            'wisata' => ['/wisata', 'Publik/Ekonomi/Wisata'],
            'belanja' => ['/belanja', 'Publik/Ekonomi/Belanja'],
            'ppid beranda' => ['/ppid', 'Publik/Ppid/Beranda'],
            'ppid dasar hukum' => ['/ppid/dasar-hukum', 'Publik/Ppid/DasarHukum'],
            'ppid berkala' => ['/ppid/berkala', 'Publik/Ppid/Informasi'],
            'ppid serta-merta' => ['/ppid/serta-merta', 'Publik/Ppid/Informasi'],
            'ppid setiap-saat' => ['/ppid/setiap-saat', 'Publik/Ppid/Informasi'],
            'ppid permohonan' => ['/ppid/permintaan', 'Publik/Ppid/Permohonan'],
            'ppid lacak' => ['/ppid/permintaan/lacak', 'Publik/Ppid/Lacak'],
            'pengaduan' => ['/pengaduan', 'Publik/Pengaduan/Kirim'],
            'pengaduan lacak' => ['/pengaduan/lacak', 'Publik/Pengaduan/Lacak'],
            'peta' => ['/listing', 'Publik/Peta/Listing'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('halamanPublik')]
    public function test_halaman_publik_dapat_diakses_tanpa_login(string $alamat, string $komponen): void
    {
        $this->get($alamat)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($komponen));
    }

    public function test_infografis_mengalihkan_ke_sub_tab_pertama(): void
    {
        // PRD Bagian 11: tidak ada halaman "hub" tersendiri.
        $this->get('/infografis')->assertRedirect('/infografis/penduduk');
    }

    public function test_jenis_informasi_ppid_yang_tidak_dikenal_menghasilkan_404(): void
    {
        // Segmen {jenis} bersifat bebas, jadi ia harus menolak nilai di luar
        // ketiga kategori baku UU No. 14/2008 — bukan merender halaman kosong.
        $this->get('/ppid/karangan-bebas')->assertNotFound();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function halamanAdmin(): array
    {
        return [
            'dashboard' => ['/admin'],
            'profil' => ['/admin/profil'],
            'sotk' => ['/admin/sotk-bpd'],
            'berita' => ['/admin/berita'],
            'galeri' => ['/admin/galeri'],
            'penduduk' => ['/admin/penduduk'],
            'ekonomi' => ['/admin/ekonomi'],
            'peta' => ['/admin/peta'],
            'pengaduan' => ['/admin/pengaduan'],
            'bansos' => ['/admin/bansos'],
            'ppid' => ['/admin/ppid'],
            'pengaturan' => ['/admin/pengaturan'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('halamanAdmin')]
    public function test_halaman_admin_menolak_tamu(string $alamat): void
    {
        $this->get($alamat)->assertRedirect(route('login'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('halamanAdmin')]
    public function test_super_admin_dapat_membuka_seluruh_layar_cms(string $alamat): void
    {
        $this->actingAs($this->operator('Super Admin'))
            ->get($alamat)
            ->assertOk();
    }

    public function test_operator_konten_hanya_menjangkau_modul_yang_diizinkan(): void
    {
        $operator = $this->operator('Operator Konten');

        // Modul yang memang menjadi tanggung jawabnya.
        $this->actingAs($operator)->get('/admin/berita')->assertOk();
        $this->actingAs($operator)->get('/admin/galeri')->assertOk();

        // Menyembunyikan menu di sidebar bukan kontrol akses (PRD 12.2):
        // route-nya sendiri yang harus menolak.
        $this->actingAs($operator)->get('/admin/penduduk')->assertForbidden();
        $this->actingAs($operator)->get('/admin/pengaturan')->assertForbidden();
        $this->actingAs($operator)->get('/admin/bansos')->assertForbidden();
    }

    private function operator(string $role): User
    {
        $user = User::create([
            'village_id' => $this->village->id,
            'name' => 'Operator '.$role,
            'email' => str()->slug($role).'@desa.test',
            'password' => Hash::make('password'),
            'status_aktif' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
