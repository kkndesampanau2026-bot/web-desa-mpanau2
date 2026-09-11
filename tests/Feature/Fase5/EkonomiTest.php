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
use Inertia\Testing\AssertableInertia as Assert;
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
    // Isi /potensi dan alamat lamanya
    // ------------------------------------------------------------------

    /**
     * Seluruh kategori tampil di /potensi, termasuk Pariwisata dan Ekonomi.
     *
     * Keduanya sempat dikeluarkan karena diselipkan ke /wisata dan /belanja —
     * akibatnya dua kategori raib dari filter dan pengunjung yang menyaringnya
     * di sini selalu menemui daftar kosong.
     */
    public function test_seluruh_kategori_tampil_di_daftar_potensi(): void
    {
        foreach (['Pariwisata', 'Ekonomi', 'Pertanian'] as $i => $kategori) {
            Potential::create([
                'village_id' => $this->village->id,
                'kategori' => $kategori,
                'judul' => "Potensi {$kategori}",
                'slug' => 'potensi-'.$i,
            ]);
        }

        $data = $this->getJson('/api/v1/potensi')->assertOk()->json('data');

        $this->assertSame(['Ekonomi', 'Pariwisata', 'Pertanian'], $data['kategori_tersedia']);
        $this->assertCount(3, $data['items']);
    }

    /**
     * Chip kategori selalu lengkap, terisi maupun tidak.
     *
     * Sejak menu bercabang di bilah navigasi dihapus, barisan chip inilah
     * satu-satunya jalan menuju destinasi wisata dan katalog UMKM — chip yang
     * disembunyikan karena kosong berarti isinya tidak terjangkau sama sekali.
     */
    public function test_chip_kategori_selalu_lengkap(): void
    {
        $this->get('/potensi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/Ekonomi/Potensi')
                ->where('kategori', Potential::KATEGORI)
            );
    }

    /** Kategori Pariwisata diisi daftar destinasi, bukan kartu potensi. */
    public function test_kategori_pariwisata_diisi_destinasi_wisata(): void
    {
        TourismSpot::create([
            'village_id' => $this->village->id,
            'nama' => 'Air Terjun Mpanau', 'slug' => 'air-terjun-mpanau',
        ]);
        Potential::create([
            'village_id' => $this->village->id,
            'kategori' => 'Pariwisata', 'judul' => 'Bukit Panorama', 'slug' => 'bukit-panorama',
        ]);

        $this->get('/potensi?kategori=Pariwisata')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/Ekonomi/Potensi')
                ->where('wisata.0.nama', 'Air Terjun Mpanau')
                ->missing('data')
            );
    }

    /** Kategori Ekonomi diisi katalog UMKM, bukan kartu potensi. */
    public function test_kategori_ekonomi_diisi_katalog_umkm(): void
    {
        $this->buatProduk(['nama_produk' => 'Keripik Pisang', 'slug' => 'keripik-pisang']);
        Potential::create([
            'village_id' => $this->village->id,
            'kategori' => 'Ekonomi', 'judul' => 'Kopi Robusta', 'slug' => 'kopi-robusta',
        ]);

        $this->get('/potensi?kategori=Ekonomi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/Ekonomi/Potensi')
                ->where('produk.items.0.nama_produk', 'Keripik Pisang')
                ->missing('data')
            );
    }

    /** Pencarian produk ikut bekerja saat katalog tampil di dalam /potensi. */
    public function test_pencarian_produk_bekerja_di_dalam_potensi(): void
    {
        $this->buatProduk(['nama_produk' => 'Keripik Pisang', 'slug' => 'keripik-pisang']);
        $this->buatProduk(['nama_produk' => 'Gula Aren', 'slug' => 'gula-aren']);

        $this->get('/potensi?kategori=Ekonomi&cari=Keripik')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filter.cari', 'Keripik')
                ->has('produk.items', 1)
                ->where('produk.items.0.nama_produk', 'Keripik Pisang')
            );
    }

    /** Kategori biasa tetap menampilkan kartu potensi seperti sedia kala. */
    public function test_kategori_biasa_tetap_menampilkan_kartu_potensi(): void
    {
        Potential::create([
            'village_id' => $this->village->id,
            'kategori' => 'Pertanian', 'judul' => 'Sawah Produktif', 'slug' => 'sawah',
        ]);

        $this->get('/potensi?kategori=Pertanian')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.items.0.judul', 'Sawah Produktif')
                ->missing('produk')
                ->missing('wisata')
            );
    }

    /**
     * Kategori produk pada tab Ekonomi memakai `jenis`, bukan `kategori`.
     *
     * Keduanya sempat berbagi satu nama parameter. Akibatnya menyaring
     * "Makanan" lalu membuka salah satu produknya membuat halaman detail
     * membaca "Makanan" sebagai kategori potensi — dan berakhir 404.
     */
    public function test_penyaring_kategori_produk_terpisah_dari_kategori_potensi(): void
    {
        $this->buatProduk([
            'nama_produk' => 'Keripik Pisang', 'slug' => 'keripik-pisang', 'kategori' => 'Makanan',
        ]);
        $this->buatProduk([
            'nama_produk' => 'Tikar Rotan', 'slug' => 'tikar-rotan', 'kategori' => 'Kerajinan',
        ]);

        $this->get('/potensi?kategori=Ekonomi&jenis=Makanan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/Ekonomi/Potensi')
                ->where('filter.jenis', 'Makanan')
                ->has('produk.items', 1)
                ->where('produk.items.0.nama_produk', 'Keripik Pisang')
            );
    }

    /**
     * Alamat lama tidak boleh berakhir 404: tautannya sudah terlanjur dibagikan
     * warga dan diindeks mesin pencari.
     */
    public function test_alamat_lama_dialihkan_ke_potensi(): void
    {
        $this->get('/wisata')->assertRedirect('/potensi?kategori=Pariwisata');
        $this->get('/wisata/air-terjun')
            ->assertRedirect('/potensi/air-terjun?kategori=Pariwisata');

        $this->get('/ekonomi')->assertRedirect('/potensi?kategori=Ekonomi');
        $this->get('/ekonomi/keripik-pisang')
            ->assertRedirect('/potensi/keripik-pisang?kategori=Ekonomi');

        $this->get('/belanja')->assertRedirect('/potensi?kategori=Ekonomi');
        $this->get('/belanja/keripik-pisang')
            ->assertRedirect('/potensi/keripik-pisang?kategori=Ekonomi');
    }

    /**
     * Detail destinasi dan produk tinggal di bawah /potensi, dengan kategori
     * yang ikut terbawa dari daftarnya.
     */
    public function test_detail_wisata_dan_produk_dibuka_di_bawah_potensi(): void
    {
        TourismSpot::create([
            'village_id' => $this->village->id,
            'nama' => 'Air Terjun Mpanau', 'slug' => 'air-terjun-mpanau',
        ]);
        $this->buatProduk(['nama_produk' => 'Keripik Pisang', 'slug' => 'keripik-pisang']);

        $this->get('/potensi/air-terjun-mpanau?kategori=Pariwisata')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/Ekonomi/WisataDetail')
                ->where('wisata.nama', 'Air Terjun Mpanau')
            );

        $this->get('/potensi/keripik-pisang?kategori=Ekonomi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/Ekonomi/ProdukDetail')
                ->where('produk.nama_produk', 'Keripik Pisang')
            );
    }

    /**
     * Tombol "kembali" memulangkan pengunjung ke daftar yang tadi dibuka —
     * lengkap dengan kategori, penyaring, kata kunci, dan nomor halamannya.
     *
     * Inilah alasan halaman detail dipindahkan ke bawah /potensi. Sewaktu
     * detail produk tinggal di /ekonomi/<slug>, tombol itu tidak punya cara
     * mengetahui daftar mana yang tadi dibuka dan selalu mendarat di /ekonomi.
     */
    public function test_tautan_kembali_memulihkan_keadaan_daftar(): void
    {
        $this->buatProduk([
            'nama_produk' => 'Keripik Pisang', 'slug' => 'keripik-pisang', 'kategori' => 'Makanan',
        ]);

        $this->get('/potensi/keripik-pisang?kategori=Ekonomi&jenis=Makanan&cari=Keripik&page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'kembali',
                    '/potensi?cari=Keripik&jenis=Makanan&kategori=Ekonomi&page=2'
                )
            );
    }

    /**
     * Seluruh isi potensi dapat dibuka pengunjung, bukan berhenti di ringkasan
     * kartu pada daftar.
     */
    public function test_detail_potensi_dapat_dibuka(): void
    {
        Potential::create([
            'village_id' => $this->village->id,
            'kategori' => 'Pariwisata', 'judul' => 'Air Terjun', 'slug' => 'air-terjun',
            'deskripsi' => 'Air terjun bertingkat di hulu sungai.',
        ]);

        $this->get('/potensi/air-terjun')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/Ekonomi/PotensiDetail')
                ->where('potensi.judul', 'Air Terjun')
                ->where('potensi.deskripsi', 'Air terjun bertingkat di hulu sungai.')
            );
    }

    public function test_detail_potensi_yang_disembunyikan_menghasilkan_404(): void
    {
        Potential::create([
            'village_id' => $this->village->id,
            'kategori' => 'Pertanian', 'judul' => 'Draf', 'slug' => 'draf',
            'status_tampil' => false,
        ]);

        $this->get('/potensi/draf')->assertNotFound();
    }

    /**
     * Sidebar halaman detail menawarkan isi lain dari jenis yang sama: tanpa
     * isi yang sedang dibuka, tanpa isi yang disembunyikan, dan dengan isi
     * sekategori didahulukan.
     */
    public function test_detail_potensi_wisata_dan_produk_menyertakan_isi_lainnya(): void
    {
        foreach ([
            ['Pertanian', 'Sawah Irigasi', 'sawah-irigasi', true],
            ['Pariwisata', 'Air Terjun', 'air-terjun', true],
            ['Pertanian', 'Kebun Kakao', 'kebun-kakao', true],
            ['Pertanian', 'Draf', 'draf', false],
        ] as [$kategori, $judul, $slug, $tampil]) {
            Potential::create([
                'village_id' => $this->village->id,
                'kategori' => $kategori, 'judul' => $judul, 'slug' => $slug,
                'status_tampil' => $tampil,
            ]);
        }

        $this->get('/potensi/sawah-irigasi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('potensi_lainnya', 2)
                ->where('potensi_lainnya.0.judul', 'Kebun Kakao')
                ->where('potensi_lainnya.1.judul', 'Air Terjun')
            );

        foreach ([
            ['Air Terjun Mpanau', 'air-terjun-mpanau', true],
            ['Bukit Panorama', 'bukit-panorama', true],
            ['Belum Siap', 'belum-siap', false],
        ] as [$nama, $slug, $tampil]) {
            TourismSpot::create([
                'village_id' => $this->village->id,
                'nama' => $nama, 'slug' => $slug, 'status_tampil' => $tampil,
            ]);
        }

        $this->get('/potensi/air-terjun-mpanau?kategori=Pariwisata')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('wisata_lainnya', 1)
                ->where('wisata_lainnya.0.nama', 'Bukit Panorama')
            );

        $this->buatProduk([
            'nama_produk' => 'Keripik Pisang', 'slug' => 'keripik-pisang', 'kategori' => 'Makanan',
        ]);
        $this->buatProduk(['nama_produk' => 'Air Nira', 'kategori' => 'Minuman']);
        $this->buatProduk(['nama_produk' => 'Sambal Roa', 'kategori' => 'Makanan']);
        $this->buatProduk([
            'nama_produk' => 'Belum Dimoderasi', 'kategori' => 'Makanan', 'status_tampil' => false,
        ]);

        $this->get('/potensi/keripik-pisang?kategori=Ekonomi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('produk_lainnya', 2)
                ->where('produk_lainnya.0.nama_produk', 'Sambal Roa')
                ->where('produk_lainnya.1.nama_produk', 'Air Nira')
            );
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
