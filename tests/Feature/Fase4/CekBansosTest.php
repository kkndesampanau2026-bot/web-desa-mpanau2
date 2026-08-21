<?php

namespace Tests\Feature\Fase4;

use App\Models\BansosRecipient;
use App\Models\BansosSearchLog;
use App\Models\BansosType;
use App\Models\Village;
use App\Services\BansosSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Fitur Cek Penerima Bansos — PRD 6.6, 10.3, 12.2 & 15.
 *
 * Ini fitur publik paling sensitif pada sistem: ia menjawab "apakah orang ini
 * menerima bantuan?" kepada siapa pun tanpa login. Berkas ini menguji setiap
 * lapis pertahanannya.
 */
class CekBansosTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    private BansosType $jenis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->village = Village::create([
            'nama' => 'Desa Uji', 'slug' => 'desa-uji', 'is_active' => true,
        ]);

        $this->jenis = BansosType::create([
            'village_id' => $this->village->id,
            'nama' => 'BLT Dana Desa',
            'slug' => 'blt-dana-desa',
        ]);

        // Pembatasan laju dinonaktifkan secara bawaan agar tiap test dapat
        // memanggil endpoint berulang kali; test khusus rate limit
        // mengaktifkannya kembali sendiri.
        RateLimiter::clear('cek-bansos');
    }

    private function buatPenerima(array $atribut = []): BansosRecipient
    {
        return BansosRecipient::create(array_merge([
            'village_id' => $this->village->id,
            'bansos_type_id' => $this->jenis->id,
            'nama' => 'Budi Santoso',
            'nik' => '7210010126001234',
            'tahun_anggaran' => 2026,
            'status' => 'aktif',
        ], $atribut));
    }

    private function cek(string $nama, string $digit)
    {
        return $this->postJson('/api/v1/infografis/bansos/cek', [
            'nama' => $nama,
            'empat_digit_nik' => $digit,
        ]);
    }

    // ------------------------------------------------------------------
    // Pencocokan
    // ------------------------------------------------------------------

    public function test_pencarian_dengan_nama_dan_empat_digit_yang_benar_menemukan_data(): void
    {
        $this->buatPenerima();

        $this->cek('Budi Santoso', '1234')
            ->assertOk()
            ->assertJsonPath('data.ditemukan', true)
            ->assertJsonPath('data.hasil.0.jenis_bantuan', 'BLT Dana Desa')
            ->assertJsonPath('data.hasil.0.status', 'aktif');
    }

    /** Kedua faktor wajib cocok — nama benar saja tidak cukup. */
    public function test_nama_benar_dengan_digit_salah_tidak_menemukan_apa_pun(): void
    {
        $this->buatPenerima();

        $this->cek('Budi Santoso', '9999')
            ->assertOk()
            ->assertJsonPath('data.ditemukan', false)
            ->assertJsonPath('data.hasil', []);
    }

    public function test_digit_benar_dengan_nama_salah_tidak_menemukan_apa_pun(): void
    {
        $this->buatPenerima();

        $this->cek('Orang Lain', '1234')
            ->assertOk()
            ->assertJsonPath('data.ditemukan', false);
    }

    public function test_pencocokan_nama_mengabaikan_kapitalisasi_dan_spasi_berlebih(): void
    {
        $this->buatPenerima();

        $this->cek('  budi   santoso  ', '1234')
            ->assertOk()
            ->assertJsonPath('data.ditemukan', true);
    }

    /** Penerima yang bantuannya dicabut tidak lagi muncul sebagai penerima aktif. */
    public function test_penerima_berstatus_dicabut_tidak_ditemukan(): void
    {
        $this->buatPenerima(['status' => 'dicabut']);

        $this->cek('Budi Santoso', '1234')
            ->assertOk()
            ->assertJsonPath('data.ditemukan', false);
    }

    // ------------------------------------------------------------------
    // Minimalisasi data pada hasil
    // ------------------------------------------------------------------

    /** PRD 6.6 mencontohkan penyamaran seperti "Budi S***". */
    public function test_nama_pada_hasil_ditampilkan_tersamar(): void
    {
        $this->buatPenerima();

        $this->cek('Budi Santoso', '1234')
            ->assertOk()
            ->assertJsonPath('data.hasil.0.nama', 'Budi S******');
    }

    public function test_nik_tidak_pernah_dikembalikan_pada_hasil_pencarian(): void
    {
        $this->buatPenerima();

        $isi = $this->cek('Budi Santoso', '1234')->assertOk()->getContent();

        $this->assertStringNotContainsString('7210010126001234', $isi);
        $this->assertStringNotContainsString('nik', strtolower($isi));
    }

    /**
     * Besaran bantuan yang diterima seseorang bukan urusan tetangganya;
     * hanya tampil bila admin sengaja mempublikasikannya (PRD 6.6).
     */
    public function test_nominal_disembunyikan_kecuali_admin_mempublikasikannya(): void
    {
        $this->buatPenerima(['nominal' => 600000, 'nominal_publik' => false]);

        $this->cek('Budi Santoso', '1234')
            ->assertOk()
            ->assertJsonPath('data.hasil.0.nominal', null);

        BansosRecipient::first()->update(['nominal_publik' => true]);

        $this->cek('Budi Santoso', '1234')
            ->assertOk()
            ->assertJsonPath('data.hasil.0.nominal', 600000);
    }

    // ------------------------------------------------------------------
    // Anti-enumerasi
    // ------------------------------------------------------------------

    /**
     * Pesan kegagalan tidak boleh membedakan "nama tidak ada" dari "digit
     * salah" — perbedaan sekecil apa pun dapat dipakai mempersempit tebakan.
     */
    public function test_pesan_kegagalan_selalu_netral_dan_identik(): void
    {
        $this->buatPenerima();

        $namaSalah = $this->cek('Nama Tidak Ada', '1234')->json('data.pesan');
        $digitSalah = $this->cek('Budi Santoso', '0000')->json('data.pesan');
        $keduanyaSalah = $this->cek('Entah Siapa', '5555')->json('data.pesan');

        $this->assertSame($namaSalah, $digitSalah);
        $this->assertSame($digitSalah, $keduanyaSalah);
        $this->assertSame(BansosSearchService::PESAN_NETRAL, $namaSalah);
    }

    /**
     * Status HTTP pun tidak boleh membedakan hasil — 404 saat tidak ketemu
     * akan membocorkan keberadaan data lewat kode respons.
     */
    public function test_status_http_sama_baik_ditemukan_maupun_tidak(): void
    {
        $this->buatPenerima();

        $this->cek('Budi Santoso', '1234')->assertStatus(200);
        $this->cek('Siapa Pun', '0000')->assertStatus(200);
    }

    public function test_tidak_ada_endpoint_yang_mengembalikan_daftar_penerima(): void
    {
        $this->buatPenerima();

        // Beberapa pola URL yang lazim dicoba untuk memperoleh daftar massal.
        foreach ([
            '/api/v1/bansos/penerima',
            '/api/v1/infografis/bansos/penerima',
            '/api/v1/bansos-recipients',
        ] as $url) {
            $this->getJson($url)->assertStatus(404);
        }

        // Endpoint agregat yang sah pun tidak boleh memuat nama seorang pun.
        $this->assertStringNotContainsString(
            'Budi Santoso',
            $this->getJson('/api/v1/infografis/bansos')->getContent()
        );
    }

    public function test_masukan_bukan_empat_digit_ditolak(): void
    {
        foreach (['123', '12345', 'abcd', ''] as $tidakValid) {
            $this->cek('Budi Santoso', $tidakValid)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['empat_digit_nik']);
        }
    }

    /** NIK penuh tidak boleh diterima — warga tak perlu menyerahkannya. */
    public function test_nik_penuh_ditolak_sebagai_masukan(): void
    {
        $this->buatPenerima();

        $this->cek('Budi Santoso', '7210010126001234')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['empat_digit_nik']);
    }

    // ------------------------------------------------------------------
    // Pembatasan laju & pemantauan
    // ------------------------------------------------------------------

    /** Pertahanan utama terhadap enumerasi — PRD 6.6 & 15. */
    public function test_pencarian_dibatasi_sepuluh_kali_per_menit(): void
    {
        $this->buatPenerima();

        for ($i = 0; $i < 10; $i++) {
            $this->cek('Budi Santoso', '1234')->assertOk();
        }

        $this->cek('Budi Santoso', '1234')
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_setiap_pencarian_tercatat_untuk_deteksi_anomali(): void
    {
        $this->buatPenerima();

        $this->cek('Budi Santoso', '1234')->assertOk();
        $this->cek('Nama Lain', '0000')->assertOk();

        $this->assertSame(2, BansosSearchLog::count());
        $this->assertSame(1, BansosSearchLog::where('ditemukan', true)->count());
    }

    /**
     * Log keamanan tidak boleh menjadi salinan data yang ia lindungi:
     * yang dicari tidak pernah ikut tersimpan.
     */
    public function test_log_pencarian_tidak_menyimpan_nama_maupun_nik_yang_dicari(): void
    {
        $this->buatPenerima();
        $this->cek('Budi Santoso', '1234')->assertOk();

        $log = json_encode(BansosSearchLog::first()->toArray());

        $this->assertStringNotContainsString('Budi', $log);
        $this->assertStringNotContainsString('1234', $log);
        $this->assertStringNotContainsString('127.0.0.1', $log);
    }
}
