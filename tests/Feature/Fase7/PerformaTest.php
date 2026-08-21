<?php

namespace Tests\Feature\Fase7;

use App\Models\BansosRecipient;
use App\Models\BansosType;
use App\Models\Gallery;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\PointOfInterest;
use App\Models\Product;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Audit performa endpoint publik — PRD 12.1.
 *
 * Fokusnya bukan mengukur waktu (yang bergantung mesin), melainkan
 * MENGHITUNG JUMLAH QUERY. Masalah N+1 tidak terlihat pada data contoh yang
 * sedikit, tetapi meledak saat desa mengisi ratusan record — dan justru
 * itulah keadaan yang tidak dapat diuji manual sebelum terlambat.
 */
class PerformaTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();

        $this->village = Village::create([
            'nama' => 'Desa Uji', 'slug' => 'desa-uji', 'is_active' => true,
        ]);

        // Cache dikosongkan agar yang terukur adalah query sesungguhnya,
        // bukan hasil cache dari test sebelumnya.
        Cache::flush();
    }

    /**
     * Menghitung jumlah query yang dipicu satu permintaan GET.
     *
     * Permintaan pemanasan dijalankan lebih dulu dan hasilnya dibuang.
     * Alasannya: middleware pencatat kunjungan menulis log + agregat pada
     * kunjungan PERTAMA sebuah sesi, lalu berhenti menulis pada kunjungan
     * berikutnya. Tanpa pemanasan, selisih satu query dari efek samping itu
     * akan terbaca seolah-olah masalah N+1.
     */
    private function ukurQuery(string $url): int
    {
        $this->getJson($url);   // pemanasan — menetralkan pencatat kunjungan

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson($url);

        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    }

    private function buatBerita(int $jumlah): void
    {
        // firstOrCreate: helper ini dipanggil dua kali dalam satu test
        // (data sedikit lalu data banyak), sehingga kategori & penulis yang
        // sama tidak boleh dibuat ulang.
        $kategori = NewsCategory::firstOrCreate(
            ['village_id' => $this->village->id, 'slug' => 'umum'],
            ['nama' => 'Umum']
        );

        $penulis = User::firstOrCreate(
            ['email' => 'penulis@desa.test'],
            [
                'village_id' => $this->village->id,
                'name' => 'Penulis',
                'password' => Hash::make('x'),
                'status_aktif' => true,
            ]
        );

        for ($i = 1; $i <= $jumlah; $i++) {
            News::create([
                'village_id' => $this->village->id,
                'news_category_id' => $kategori->id,
                'penulis_id' => $penulis->id,
                'judul' => "Berita {$i}",
                'slug' => "berita-{$i}",
                'konten' => '<p>Isi.</p>',
                'status' => 'published',
                'tanggal_publish' => now()->subDays($i),
            ]);
        }
    }

    /**
     * Daftar berita memuat kategori & penulis tiap artikel. Tanpa eager
     * loading, 20 artikel berarti 40 query tambahan.
     */
    public function test_daftar_berita_tidak_menimbulkan_query_n_plus_1(): void
    {
        $this->buatBerita(3);
        $sedikit = $this->ukurQuery('/api/v1/berita?per_page=20');

        // Basis data dikosongkan lalu diisi lebih banyak, dengan cache bersih.
        News::query()->forceDelete();
        Cache::flush();
        $this->buatBerita(15);
        $banyak = $this->ukurQuery('/api/v1/berita?per_page=20');

        $this->assertSame(
            $sedikit,
            $banyak,
            "Jumlah query berubah dari {$sedikit} menjadi {$banyak} saat data bertambah — "
            .'menandakan relasi belum di-eager-load.'
        );
    }

    public function test_katalog_produk_tidak_menimbulkan_query_n_plus_1(): void
    {
        $buat = function (int $n) {
            for ($i = 1; $i <= $n; $i++) {
                Product::create([
                    'village_id' => $this->village->id,
                    'nama_produk' => "Produk {$i}",
                    'slug' => "produk-{$i}",
                    'nama_penjual' => 'Penjual',
                    'status_tampil' => true,
                ]);
            }
        };

        $buat(3);
        $sedikit = $this->ukurQuery('/api/v1/produk?per_page=20');

        Product::query()->forceDelete();
        Cache::flush();
        $buat(15);
        $banyak = $this->ukurQuery('/api/v1/produk?per_page=20');

        $this->assertSame($sedikit, $banyak, 'Relasi foto produk belum di-eager-load.');
    }

    public function test_titik_lokasi_peta_tidak_menimbulkan_query_n_plus_1(): void
    {
        $buat = function (int $n) {
            for ($i = 1; $i <= $n; $i++) {
                PointOfInterest::create([
                    'village_id' => $this->village->id,
                    'nama' => "Titik {$i}",
                    'kategori' => 'Lainnya',
                    'latitude' => -0.95 - ($i / 10000),
                    'longitude' => 119.90 + ($i / 10000),
                    'status_tampil' => true,
                ]);
            }
        };

        $buat(3);
        $sedikit = $this->ukurQuery('/api/v1/points-of-interest');

        PointOfInterest::query()->delete();
        Cache::flush();
        $buat(15);
        $banyak = $this->ukurQuery('/api/v1/points-of-interest');

        $this->assertSame($sedikit, $banyak, 'Relasi dusun/wisata/produk pada POI belum di-eager-load.');
    }

    public function test_galeri_tidak_menimbulkan_query_n_plus_1(): void
    {
        $buat = function (int $n) {
            for ($i = 1; $i <= $n; $i++) {
                Gallery::create([
                    'village_id' => $this->village->id,
                    'nama_album' => "Album {$i}",
                    'slug' => "album-{$i}",
                    'status_tampil' => true,
                ]);
            }
        };

        $buat(3);
        $sedikit = $this->ukurQuery('/api/v1/galeri?per_page=20');

        Gallery::query()->delete();
        Cache::flush();
        $buat(15);
        $banyak = $this->ukurQuery('/api/v1/galeri?per_page=20');

        $this->assertSame($sedikit, $banyak, 'Perhitungan jumlah foto album belum memakai withCount.');
    }

    public function test_infografis_bansos_tidak_menimbulkan_query_n_plus_1(): void
    {
        $buat = function (int $n) {
            for ($i = 1; $i <= $n; $i++) {
                $jenis = BansosType::create([
                    'village_id' => $this->village->id,
                    'nama' => "Bantuan {$i}",
                    'slug' => "bantuan-{$i}",
                ]);

                BansosRecipient::create([
                    'village_id' => $this->village->id,
                    'bansos_type_id' => $jenis->id,
                    'nama' => "Penerima {$i}",
                    'nik' => str_pad((string) (9999000000000000 + $i), 16, '0', STR_PAD_LEFT),
                    'tahun_anggaran' => 2026,
                    'status' => 'aktif',
                ]);
            }
        };

        $buat(2);
        $sedikit = $this->ukurQuery('/api/v1/infografis/bansos');

        BansosRecipient::query()->forceDelete();
        BansosType::query()->delete();
        Cache::flush();
        $buat(10);
        $banyak = $this->ukurQuery('/api/v1/infografis/bansos');

        $this->assertSame($sedikit, $banyak, 'Perhitungan jumlah penerima per jenis belum memakai withCount.');
    }

    /**
     * Cache memang bertugas menekan beban basis data (PRD 7.3); test ini
     * memastikan ia benar-benar bekerja, bukan sekadar dipasang.
     */
    public function test_permintaan_kedua_dilayani_cache_tanpa_menyentuh_basis_data(): void
    {
        $this->buatBerita(2);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/settings');
        $pertama = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->getJson('/api/v1/settings');
        $kedua = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(
            $pertama,
            $kedua,
            'Endpoint pengaturan seharusnya dilayani cache pada permintaan berikutnya.'
        );
    }
}
