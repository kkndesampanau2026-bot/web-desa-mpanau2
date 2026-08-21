<?php

namespace Tests\Feature\Fase2;

use App\Models\Village;
use App\Models\VisitorLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Statistik Kunjungan — PRD 6.16 & 12.2.
 */
class StatistikKunjunganTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Village::create(['nama' => 'Desa Uji', 'slug' => 'desa-uji', 'is_active' => true]);
        Cache::flush();
    }

    public function test_kunjungan_halaman_publik_tercatat(): void
    {
        $this->getJson('/api/v1/profil')->assertOk();

        $this->assertSame(1, VisitorLog::count());
        $this->assertDatabaseHas('visitor_daily_summary', ['jumlah_unique_visit' => 1]);
    }

    /**
     * PRD 6.16 menuntut dedup per sesi/hari agar reload halaman tidak
     * menggandakan hitungan.
     */
    public function test_kunjungan_berulang_dari_sesi_sama_hanya_dihitung_sekali(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/v1/profil')->assertOk();
        }

        $this->assertSame(1, VisitorLog::count());
        $this->assertDatabaseHas('visitor_daily_summary', ['jumlah_unique_visit' => 1]);
    }

    public function test_pengunjung_berbeda_dihitung_terpisah(): void
    {
        $this->getJson('/api/v1/profil')->assertOk();

        // User agent berbeda menghasilkan session_hash berbeda.
        $this->withHeader('User-Agent', 'Peramban Lain/1.0')
            ->getJson('/api/v1/profil')->assertOk();

        $this->assertSame(2, VisitorLog::count());
        $this->assertDatabaseHas('visitor_daily_summary', ['jumlah_unique_visit' => 2]);
    }

    /**
     * Jaminan privasi inti PRD 6.16: yang tersimpan adalah hash, alamat IP
     * mentah tidak pernah menyentuh basis data.
     */
    public function test_alamat_ip_mentah_tidak_pernah_disimpan(): void
    {
        $this->getJson('/api/v1/profil')->assertOk();

        $log = VisitorLog::first();

        $this->assertSame(64, strlen($log->session_hash));   // SHA-256 hex
        $this->assertStringNotContainsString('127.0.0.1', json_encode($log->toArray()));
    }

    public function test_endpoint_statistik_mengembalikan_tujuh_kategori_sesuai_prd(): void
    {
        $data = $this->getJson('/api/v1/visitor-stats')->assertOk()->json('data');

        $this->assertSame([
            'hari_ini', 'kemarin', 'minggu_ini', 'minggu_lalu',
            'bulan_ini', 'bulan_lalu', 'total',
        ], array_keys($data));
    }

    /**
     * Widget statistik tampil di setiap halaman; bila ia ikut tercatat,
     * satu kali kunjungan akan terhitung dua kali.
     */
    public function test_endpoint_statistik_tidak_ikut_menambah_hitungan_kunjungan(): void
    {
        $this->getJson('/api/v1/visitor-stats')->assertOk();

        $this->assertSame(0, VisitorLog::count());
    }
}
