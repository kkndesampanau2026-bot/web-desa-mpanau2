<?php

namespace Tests\Feature\Publik;

use App\Models\News;
use App\Models\Village;
use App\Models\VillageProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Beranda — halaman Inertia pertama yang dilayani Laravel secara langsung.
 *
 * Menggantikan `ExampleTest` bawaan Laravel, yang dulu hanya memastikan `/`
 * mengembalikan 200 saat alamat itu masih menampilkan halaman `welcome`.
 * Sejak situs publik disajikan dari sini, "200" saja tidak lagi memadai:
 * yang perlu dijamin adalah komponen yang benar terpilih beserta datanya.
 */
class BerandaTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();

        $this->village = Village::create([
            'nama' => 'Desa Uji',
            'slug' => 'desa-uji',
            'is_active' => true,
        ]);
    }

    public function test_beranda_merender_komponen_inertia_beserta_prop_bersama(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Publik/Beranda')
                    // Identitas desa & statistik ikut pada setiap respons publik
                    // agar header dan footer terisi tanpa permintaan tambahan.
                    ->where('pengaturan.nama_desa', 'Desa Uji')
                    ->has('statistik_kunjungan')
                    ->where('auth.user', null)
            );
    }

    public function test_beranda_tetap_tampil_saat_profil_desa_belum_diisi(): void
    {
        // Empty-state (PRD 3.2): halaman tidak boleh gagal hanya karena admin
        // desa belum sempat mengisi visi/misi.
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('profil', null));
    }

    public function test_beranda_memuat_tiga_berita_terbaru_yang_sudah_tayang(): void
    {
        VillageProfile::create([
            'village_id' => $this->village->id,
            'visi' => 'Desa maju dan sejahtera',
        ]);

        foreach (range(1, 4) as $i) {
            News::create([
                'village_id' => $this->village->id,
                'judul' => "Berita {$i}",
                'slug' => "berita-{$i}",
                'konten' => 'Isi berita.',
                'status' => 'published',
                'tanggal_publish' => now()->subDays(5 - $i),
            ]);
        }

        News::create([
            'village_id' => $this->village->id,
            'judul' => 'Masih draf',
            'slug' => 'masih-draf',
            'konten' => 'Belum siap.',
            'status' => 'draft',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('profil.visi', 'Desa maju dan sejahtera')
                    ->has('berita_terbaru', 3)
                    // Terbaru lebih dulu, dan draf tidak pernah ikut.
                    ->where('berita_terbaru.0.judul', 'Berita 4')
            );
    }
}
