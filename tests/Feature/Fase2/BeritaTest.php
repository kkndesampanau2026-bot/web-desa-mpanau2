<?php

namespace Tests\Feature\Fase2;

use App\Models\News;
use App\Models\NewsCategory;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Modul Berita — PRD 6.10.
 */
class BeritaTest extends TestCase
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

    private function operator(string $role = 'Admin Utama'): User
    {
        $user = User::create([
            'village_id' => $this->village->id,
            'name' => 'Operator',
            'email' => uniqid().'@desa.test',
            'password' => Hash::make('rahasia123'),
            'status_aktif' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function buatBerita(array $atribut = []): News
    {
        return News::create(array_merge([
            'village_id' => $this->village->id,
            'judul' => 'Berita Contoh',
            'slug' => 'berita-contoh-'.uniqid(),
            'konten' => '<p>Isi berita.</p>',
            'status' => 'published',
            'tanggal_publish' => now()->subDay(),
        ], $atribut));
    }

    public function test_halaman_detail_berita_menyertakan_berita_terbaru_tanpa_artikel_itu_sendiri(): void
    {
        $dibuka = $this->buatBerita(['judul' => 'Sedang Dibuka', 'tanggal_publish' => now()->subHour()]);
        $this->buatBerita(['judul' => 'Berita Lain', 'tanggal_publish' => now()->subDays(2)]);
        $this->buatBerita(['judul' => 'Masih Draft', 'status' => 'draft', 'tanggal_publish' => null]);

        $this->get("/berita/{$dibuka->slug}")
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Publik/Berita/Detail')
                ->where('berita.judul', 'Sedang Dibuka')
                ->has('berita_terbaru', 1)
                ->where('berita_terbaru.0.judul', 'Berita Lain')
            );
    }

    public function test_daftar_berita_publik_hanya_menampilkan_yang_sudah_tayang(): void
    {
        $this->buatBerita(['judul' => 'Sudah Tayang']);
        $this->buatBerita(['judul' => 'Masih Draft', 'status' => 'draft', 'tanggal_publish' => null]);
        $this->buatBerita(['judul' => 'Diarsipkan', 'status' => 'diarsipkan']);

        $response = $this->getJson('/api/v1/berita')->assertOk();

        $judul = collect($response->json('data'))->pluck('judul');
        $this->assertContains('Sudah Tayang', $judul);
        $this->assertNotContains('Masih Draft', $judul);
        $this->assertNotContains('Diarsipkan', $judul);
    }

    /**
     * Artikel berstatus `terjadwal` harus tetap tersembunyi sampai waktunya
     * tiba, lalu muncul sendiri tanpa perlu job pengubah status.
     */
    public function test_berita_terjadwal_baru_muncul_setelah_waktunya_tiba(): void
    {
        $this->buatBerita([
            'judul' => 'Tayang Besok',
            'status' => 'terjadwal',
            'tanggal_publish' => now()->addDay(),
        ]);

        $judul = collect($this->getJson('/api/v1/berita')->json('data'))->pluck('judul');
        $this->assertNotContains('Tayang Besok', $judul);

        $this->travel(2)->days();

        $judul = collect($this->getJson('/api/v1/berita')->json('data'))->pluck('judul');
        $this->assertContains('Tayang Besok', $judul);
    }

    public function test_detail_berita_menambah_jumlah_dilihat(): void
    {
        $berita = $this->buatBerita();

        $this->getJson("/api/v1/berita/{$berita->slug}")
            ->assertOk()
            ->assertJsonPath('data.judul', $berita->judul);

        $this->assertSame(1, $berita->fresh()->jumlah_dilihat);
    }

    public function test_berita_belum_tayang_tidak_dapat_diakses_lewat_slug(): void
    {
        $berita = $this->buatBerita(['status' => 'draft', 'tanggal_publish' => null]);

        $this->getJson("/api/v1/berita/{$berita->slug}")->assertStatus(404);
    }

    public function test_admin_membuat_berita_menghasilkan_slug_unik(): void
    {
        Sanctum::actingAs($this->operator());

        $payload = [
            'judul' => 'Musyawarah Desa 2026',
            'konten' => '<p>Isi.</p>',
            'status' => 'published',
        ];

        $this->postJson('/api/v1/admin/berita', $payload)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'musyawarah-desa-2026');

        // Judul sama untuk kedua kalinya tidak boleh gagal — slug diberi imbuhan.
        $this->postJson('/api/v1/admin/berita', $payload)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'musyawarah-desa-2026-2');
    }

    /**
     * PRD 6.10 & 12.2 mewajibkan sanitasi HTML pada konten rich text.
     */
    public function test_konten_berita_disanitasi_dari_skrip_berbahaya(): void
    {
        Sanctum::actingAs($this->operator());

        $response = $this->postJson('/api/v1/admin/berita', [
            'judul' => 'Uji XSS',
            'konten' => '<p>Aman</p><script>alert("xss")</script><img src=x onerror="alert(1)">',
            'status' => 'draft',
        ])->assertCreated();

        $konten = News::find($response->json('data.id'))->konten;

        $this->assertStringNotContainsString('<script', $konten);
        $this->assertStringNotContainsString('onerror', $konten);
        $this->assertStringContainsString('Aman', $konten);
    }

    public function test_slug_tidak_berubah_saat_judul_tidak_disunting(): void
    {
        Sanctum::actingAs($this->operator());
        $berita = $this->buatBerita(['judul' => 'Judul Tetap', 'slug' => 'judul-tetap']);

        $this->putJson("/api/v1/admin/berita/{$berita->id}", [
            'judul' => 'Judul Tetap',
            'konten' => '<p>Konten diperbarui.</p>',
            'status' => 'published',
        ])->assertOk()->assertJsonPath('data.slug', 'judul-tetap');
    }

    public function test_operator_ppid_tidak_boleh_mengelola_berita(): void
    {
        Sanctum::actingAs($this->operator('Operator PPID'));

        $this->postJson('/api/v1/admin/berita', [
            'judul' => 'Percobaan',
            'konten' => '<p>x</p>',
            'status' => 'draft',
        ])->assertStatus(403);
    }

    public function test_filter_kategori_menyaring_daftar_berita(): void
    {
        $kategori = NewsCategory::create([
            'village_id' => $this->village->id,
            'nama' => 'Pembangunan',
            'slug' => 'pembangunan',
        ]);

        $this->buatBerita(['judul' => 'Masuk Kategori', 'news_category_id' => $kategori->id]);
        $this->buatBerita(['judul' => 'Tanpa Kategori']);

        $judul = collect(
            $this->getJson('/api/v1/berita?kategori=pembangunan')->json('data')
        )->pluck('judul');

        $this->assertContains('Masuk Kategori', $judul);
        $this->assertNotContains('Tanpa Kategori', $judul);
    }
}
