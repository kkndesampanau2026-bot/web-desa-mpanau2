<?php

namespace Tests\Feature\Fase7;

use App\Models\Village;
use App\Services\CaptchaVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Verifikasi CAPTCHA pada formulir publik — PRD 6.6 & 12.2.
 *
 * Menutup catatan DEVIASI C9 yang sebelumnya tertunda.
 */
class CaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Village::create(['nama' => 'Desa Uji', 'slug' => 'desa-uji', 'is_active' => true]);
        RateLimiter::clear('cek-bansos');
    }

    private function aktifkanCaptcha(): void
    {
        config(['captcha.secret_key' => 'kunci-uji', 'captcha.site_key' => 'situs-uji']);
    }

    private function ajukanPengaduan(array $tambahan = [])
    {
        return $this->postJson('/api/v1/pengaduan', array_merge([
            'nama' => 'Budi Santoso',
            'no_telepon_wa' => '081234567890',
            'kategori_pengaduan' => 'Kebersihan',
            'isi_pengaduan' => 'Sampah menumpuk di jalan dusun sudah dua minggu.',
        ], $tambahan));
    }

    /**
     * Situs harus tetap berfungsi sebelum desa memperoleh kunci Turnstile —
     * kalau tidak, formulir akan lumpuh di antara pemasangan dan pendaftaran
     * kunci.
     */
    public function test_formulir_tetap_berfungsi_saat_captcha_belum_dikonfigurasi(): void
    {
        config(['captcha.secret_key' => null]);

        $this->assertFalse(app(CaptchaVerifier::class)->aktif());
        $this->ajukanPengaduan()->assertCreated();
    }

    public function test_token_captcha_wajib_saat_captcha_aktif(): void
    {
        $this->aktifkanCaptcha();

        $this->ajukanPengaduan()
            ->assertStatus(422)
            ->assertJsonValidationErrors([CaptchaVerifier::FIELD]);
    }

    public function test_token_sah_meloloskan_pengiriman(): void
    {
        $this->aktifkanCaptcha();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $this->ajukanPengaduan([CaptchaVerifier::FIELD => 'token-sah'])->assertCreated();
    }

    public function test_token_palsu_ditolak(): void
    {
        $this->aktifkanCaptcha();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false]),
        ]);

        $this->ajukanPengaduan([CaptchaVerifier::FIELD => 'token-palsu'])
            ->assertStatus(422);

        $this->assertDatabaseCount('complaints', 0);
    }

    /**
     * Bila layanan CAPTCHA tak terjangkau, permintaan DITOLAK — bukan
     * diloloskan. Memilih "gagal-terbuka" akan membuat pertahanan ini dapat
     * dilumpuhkan hanya dengan mengganggu koneksi ke Cloudflare.
     */
    public function test_layanan_captcha_tidak_terjangkau_menolak_permintaan(): void
    {
        $this->aktifkanCaptcha();

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('jaringan putus');
        });

        $this->ajukanPengaduan([CaptchaVerifier::FIELD => 'token'])->assertStatus(422);

        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_captcha_juga_melindungi_cek_bansos_dan_permohonan_ppid(): void
    {
        $this->aktifkanCaptcha();

        $this->postJson('/api/v1/infografis/bansos/cek', [
            'nama' => 'Budi Santoso',
            'empat_digit_nik' => '1234',
        ])->assertStatus(422)->assertJsonValidationErrors([CaptchaVerifier::FIELD]);

        $this->postJson('/api/v1/ppid/permintaan', [
            'nama_pemohon' => 'Budi Santoso',
            'kontak' => '081234567890',
            'informasi_diminta' => 'Salinan APBDes 2026.',
            'cara_memperoleh' => 'email',
        ])->assertStatus(422)->assertJsonValidationErrors([CaptchaVerifier::FIELD]);
    }
}
