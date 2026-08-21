<?php

namespace Tests\Feature\Fase5;

use App\Models\Potential;
use App\Models\Product;
use App\Models\TourismSpot;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Modul Potensi Desa, Wisata, dan Katalog UMKM — PRD 6.11 & 6.12.
 */
class EkonomiTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->village = Village::create([
            'nama' => 'Desa Uji', 'slug' => 'desa-uji', 'is_active' => true,
        ]);
    }

    private function operator(string $role = 'Operator Konten'): User
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

    private function buatProduk(array $atribut = []): Product
    {
        static $n = 0;
        $n++;

        return Product::create(array_merge([
            'village_id' => $this->village->id,
            'nama_produk' => "Produk {$n}",
            'slug' => "produk-{$n}",
            'nama_penjual' => 'Penjual Uji',
            'status_tampil' => true,
        ], $atribut));
    }

    // ------------------------------------------------------------------
    // Potensi Desa
    // ------------------------------------------------------------------

    public function test_potensi_belum_diisi_menampilkan_empty_state(): void
    {
        $this->getJson('/api/v1/potensi')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    public function test_daftar_potensi_hanya_menampilkan_yang_ditampilkan(): void
    {
        Potential::create([
            'village_id' => $this->village->id,
            'kategori' => 'Pertanian', 'judul' => 'Sawah Produktif', 'slug' => 'sawah',
        ]);
        Potential::create([
            'village_id' => $this->village->id,
            'kategori' => 'Ekonomi', 'judul' => 'Draf Belum Siap', 'slug' => 'draf',
            'status_tampil' => false,
        ]);

        $judul = collect($this->getJson('/api/v1/potensi')->json('data.items'))->pluck('judul');

        $this->assertContains('Sawah Produktif', $judul);
        $this->assertNotContains('Draf Belum Siap', $judul);
    }

    /** Filter kategori hanya menawarkan yang benar-benar terisi. */
    public function test_kategori_tersedia_hanya_yang_punya_isi(): void
    {
        Potential::create([
            'village_id' => $this->village->id,
            'kategori' => 'Pertanian', 'judul' => 'Sawah', 'slug' => 'sawah',
        ]);

        $kategori = $this->getJson('/api/v1/potensi')->json('data.kategori_tersedia');

        $this->assertSame(['Pertanian'], $kategori);
    }

    public function test_kategori_potensi_di_luar_daftar_ditolak(): void
    {
        Sanctum::actingAs($this->operator());

        $this->postJson('/api/v1/admin/potensi', [
            'kategori' => 'Kategori Karangan',
            'judul' => 'Sesuatu',
        ])->assertStatus(422)->assertJsonValidationErrors(['kategori']);
    }

    public function test_slug_potensi_tidak_berubah_saat_judul_tetap(): void
    {
        Sanctum::actingAs($this->operator());

        $id = $this->postJson('/api/v1/admin/potensi', [
            'kategori' => 'Ekonomi', 'judul' => 'Kopi Robusta',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/admin/potensi/{$id}", [
            'kategori' => 'Ekonomi',
            'judul' => 'Kopi Robusta',
            'deskripsi' => 'Deskripsi diperbarui.',
        ])->assertOk()->assertJsonPath('data.slug', 'kopi-robusta');
    }

    // ------------------------------------------------------------------
    // Wisata
    // ------------------------------------------------------------------

    public function test_detail_wisata_menyajikan_fasilitas_dan_jam_operasional(): void
    {
        TourismSpot::create([
            'village_id' => $this->village->id,
            'nama' => 'Air Terjun Mpanau',
            'slug' => 'air-terjun-mpanau',
            'harga_tiket' => 'Gratis',
            'fasilitas' => ['Parkir', 'Toilet', 'Mushola'],
            'jam_operasional' => ['senin' => ['buka' => '08:00', 'tutup' => '17:00']],
        ]);

        $this->getJson('/api/v1/wisata/air-terjun-mpanau')
            ->assertOk()
            ->assertJsonPath('data.harga_tiket', 'Gratis')
            ->assertJsonCount(3, 'data.fasilitas')
            ->assertJsonPath('data.jam_operasional.senin.buka', '08:00');
    }

    /** PRD 6.11 memperbolehkan harga tiket berupa teks seperti "Gratis". */
    public function test_harga_tiket_dapat_berupa_teks(): void
    {
        Sanctum::actingAs($this->operator());

        $this->postJson('/api/v1/admin/wisata', [
            'nama' => 'Bukit Panorama',
            'harga_tiket' => 'Sukarela',
        ])->assertCreated()->assertJsonPath('data.harga_tiket', 'Sukarela');
    }

    public function test_wisata_disembunyikan_tidak_dapat_diakses_publik(): void
    {
        TourismSpot::create([
            'village_id' => $this->village->id,
            'nama' => 'Belum Siap', 'slug' => 'belum-siap', 'status_tampil' => false,
        ]);

        $this->getJson('/api/v1/wisata/belum-siap')->assertStatus(404);
    }

    public function test_koordinat_wisata_di_luar_rentang_ditolak(): void
    {
        Sanctum::actingAs($this->operator());

        $this->postJson('/api/v1/admin/wisata', [
            'nama' => 'Lokasi Mustahil',
            'latitude' => 120,
            'longitude' => 200,
        ])->assertStatus(422)->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    // ------------------------------------------------------------------
    // Katalog UMKM
    // ------------------------------------------------------------------

    public function test_katalog_hanya_menampilkan_produk_lolos_moderasi(): void
    {
        $this->buatProduk(['nama_produk' => 'Keripik Pisang']);
        $this->buatProduk(['nama_produk' => 'Belum Dimoderasi', 'status_tampil' => false]);

        $nama = collect($this->getJson('/api/v1/produk')->json('data'))->pluck('nama_produk');

        $this->assertContains('Keripik Pisang', $nama);
        $this->assertNotContains('Belum Dimoderasi', $nama);
    }

    /** Produk habis tetap tampil sebagai informasi, hanya diurutkan belakangan. */
    public function test_produk_habis_tetap_tampil_namun_diurutkan_setelah_yang_tersedia(): void
    {
        $this->buatProduk(['nama_produk' => 'AAA Habis', 'tersedia' => false]);
        $this->buatProduk(['nama_produk' => 'ZZZ Tersedia', 'tersedia' => true]);

        $data = $this->getJson('/api/v1/produk')->assertOk()->json('data');

        $this->assertCount(2, $data);
        $this->assertSame('ZZZ Tersedia', $data[0]['nama_produk']);
        $this->assertFalse($data[1]['tersedia']);
    }

    /**
     * Nomor WhatsApp ditulis warga dengan beragam format; semuanya harus
     * menghasilkan tautan wa.me yang sama.
     */
    public function test_nomor_whatsapp_dinormalkan_ke_format_internasional(): void
    {
        foreach ([
            '081234567890' => '6281234567890',
            '0812-3456-7890' => '6281234567890',
            '+62 812 3456 7890' => '6281234567890',
            '6281234567890' => '6281234567890',
        ] as $masukan => $harapan) {
            $produk = $this->buatProduk(['kontak_wa' => $masukan]);

            $this->assertSame(
                $harapan,
                $produk->whatsappInternasional(),
                "Format {$masukan} seharusnya menjadi {$harapan}."
            );
        }
    }

    public function test_produk_tanpa_nomor_whatsapp_tidak_menghasilkan_tautan(): void
    {
        $produk = $this->buatProduk(['kontak_wa' => null]);

        $this->assertNull($produk->whatsappInternasional());

        $this->getJson("/api/v1/produk/{$produk->slug}")
            ->assertOk()
            ->assertJsonPath('data.penjual.whatsapp_link', null);
    }

    public function test_nomor_whatsapp_tidak_masuk_akal_ditolak(): void
    {
        Sanctum::actingAs($this->operator());

        $this->postJson('/api/v1/admin/produk', [
            'nama_produk' => 'Produk Uji',
            'nama_penjual' => 'Penjual',
            'kontak_wa' => 'bukan-nomor',
        ])->assertStatus(422)->assertJsonValidationErrors(['kontak_wa']);
    }

    public function test_pencarian_produk_mencakup_nama_penjual(): void
    {
        $this->buatProduk(['nama_produk' => 'Gula Aren', 'nama_penjual' => 'Ibu Sari']);
        $this->buatProduk(['nama_produk' => 'Kopi Bubuk', 'nama_penjual' => 'Pak Budi']);

        $nama = collect($this->getJson('/api/v1/produk?cari=Sari')->json('data'))
            ->pluck('nama_produk');

        $this->assertContains('Gula Aren', $nama);
        $this->assertNotContains('Kopi Bubuk', $nama);
    }

    public function test_slug_produk_kembar_diberi_imbuhan(): void
    {
        Sanctum::actingAs($this->operator());

        $payload = ['nama_produk' => 'Keripik Pisang', 'nama_penjual' => 'Penjual'];

        $this->postJson('/api/v1/admin/produk', $payload)
            ->assertCreated()->assertJsonPath('data.slug', 'keripik-pisang');

        $this->postJson('/api/v1/admin/produk', $payload)
            ->assertCreated()->assertJsonPath('data.slug', 'keripik-pisang-2');
    }

    /**
     * Modul ini sengaja tidak memiliki keranjang maupun transaksi
     * (DEVIASI A4), jadi tidak boleh ada endpoint semacam itu.
     */
    public function test_tidak_ada_endpoint_keranjang_atau_pemesanan(): void
    {
        foreach (['/api/v1/keranjang', '/api/v1/produk/checkout', '/api/v1/pesanan'] as $url) {
            // 404 (rute tidak ada) maupun 405 (metode tidak diizinkan) sama-sama
            // membuktikan tidak ada endpoint yang MENERIMA pengiriman pesanan.
            // /produk/checkout menghasilkan 405 karena polanya bertabrakan
            // dengan GET /produk/{slug}, bukan karena ada penanganan POST.
            $this->assertContains(
                $this->postJson($url, [])->getStatusCode(),
                [404, 405],
                "Endpoint {$url} seharusnya tidak menerima POST."
            );
        }
    }

    // ------------------------------------------------------------------
    // Batas akses
    // ------------------------------------------------------------------

    public function test_operator_ppid_tidak_dapat_mengelola_modul_ekonomi(): void
    {
        Sanctum::actingAs($this->operator('Operator PPID'));

        $this->postJson('/api/v1/admin/potensi', [])->assertStatus(403);
        $this->postJson('/api/v1/admin/wisata', [])->assertStatus(403);
        $this->postJson('/api/v1/admin/produk', [])->assertStatus(403);
    }

    public function test_operator_konten_berwenang_atas_modul_ekonomi(): void
    {
        $operator = $this->operator('Operator Konten');

        $this->assertTrue($operator->can('manage-potential'));
        $this->assertTrue($operator->can('manage-tourism'));
        $this->assertTrue($operator->can('manage-product'));
    }

    public function test_perubahan_produk_tercatat_pada_audit_trail(): void
    {
        $operator = $this->operator();
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/admin/produk', [
            'nama_produk' => 'Madu Hutan',
            'nama_penjual' => 'Kelompok Tani',
        ])->assertCreated();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $operator->id,
            'deskripsi' => 'Menambah produk UMKM: Madu Hutan',
        ]);
    }
}
