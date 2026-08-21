<?php

namespace Tests\Feature\Fase3;

use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetYear;
use App\Models\IdmScore;
use App\Models\SdgsScore;
use App\Models\StuntingRecord;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Modul transparansi: APBDes, Stunting, IDM, dan SDGs Desa —
 * PRD 6.4, 6.5, 6.7, 6.8.
 */
class TransparansiTest extends TestCase
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

    /** @return array{BudgetYear, BudgetCategory, BudgetCategory} */
    private function siapkanApbdes(int $tahun = 2026, bool $publikasikan = true): array
    {
        $tahunAnggaran = BudgetYear::create([
            'village_id' => $this->village->id,
            'tahun' => $tahun,
            'publikasikan' => $publikasikan,
        ]);

        $pendapatan = BudgetCategory::create([
            'village_id' => $this->village->id,
            'kelompok' => 'Pendapatan',
            'nama' => 'Pendapatan Transfer',
        ]);

        $belanja = BudgetCategory::create([
            'village_id' => $this->village->id,
            'kelompok' => 'Belanja',
            'nama' => 'Pelaksanaan Pembangunan',
        ]);

        return [$tahunAnggaran, $pendapatan, $belanja];
    }

    private function buatItem(BudgetYear $tahun, BudgetCategory $kategori, float $jumlah): void
    {
        BudgetItem::create([
            'village_id' => $this->village->id,
            'budget_year_id' => $tahun->id,
            'budget_category_id' => $kategori->id,
            'nama_item' => 'Item Uji',
            'jumlah_anggaran' => $jumlah,
        ]);
    }

    // ------------------------------------------------------------------
    // APBDes
    // ------------------------------------------------------------------

    /** Surplus/defisit dihitung otomatis = Pendapatan − Belanja (PRD 6.4). */
    public function test_surplus_defisit_dihitung_otomatis(): void
    {
        [$tahun, $pendapatan, $belanja] = $this->siapkanApbdes();
        $this->buatItem($tahun, $pendapatan, 900_000_000);
        $this->buatItem($tahun, $belanja, 750_000_000);

        $this->getJson('/api/v1/infografis/apbdes')
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total_pendapatan', 900000000)
            ->assertJsonPath('data.ringkasan.total_belanja', 750000000)
            ->assertJsonPath('data.ringkasan.surplus_defisit', 150000000);
    }

    public function test_defisit_ditampilkan_sebagai_nilai_negatif(): void
    {
        [$tahun, $pendapatan, $belanja] = $this->siapkanApbdes();
        $this->buatItem($tahun, $pendapatan, 500_000_000);
        $this->buatItem($tahun, $belanja, 600_000_000);

        $this->getJson('/api/v1/infografis/apbdes')
            ->assertOk()
            ->assertJsonPath('data.ringkasan.surplus_defisit', -100000000);
    }

    /**
     * Tahun anggaran yang belum dipublikasikan tidak boleh bocor ke publik —
     * admin harus bisa menyusun anggaran tahun depan tanpa langsung terbuka.
     */
    public function test_tahun_anggaran_belum_dipublikasikan_tidak_tampil_publik(): void
    {
        [$tahun, $pendapatan] = $this->siapkanApbdes(2027, publikasikan: false);
        $this->buatItem($tahun, $pendapatan, 100_000_000);

        $this->getJson('/api/v1/infografis/apbdes')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_tren_antar_tahun_disusun_menaik(): void
    {
        [$t2025, $p2025] = $this->siapkanApbdes(2025);
        $this->buatItem($t2025, $p2025, 800_000_000);

        [$t2026, $p2026] = $this->siapkanApbdes(2026);
        $this->buatItem($t2026, $p2026, 950_000_000);

        $tren = $this->getJson('/api/v1/infografis/apbdes')->assertOk()->json('data.tren');

        $this->assertSame([2025, 2026], array_column($tren, 'tahun'));
        // assertEquals, bukan assertSame: JSON menuliskan 950000000.0 sebagai
        // bilangan bulat, sehingga tipenya berubah saat kembali dari respons.
        $this->assertEquals(950_000_000, $tren[1]['pendapatan']);
    }

    public function test_nilai_anggaran_negatif_ditolak(): void
    {
        Sanctum::actingAs($this->operator());
        [$tahun, $kategori] = $this->siapkanApbdes();

        $this->postJson('/api/v1/admin/apbdes/items', [
            'budget_year_id' => $tahun->id,
            'budget_category_id' => $kategori->id,
            'nama_item' => 'Item Negatif',
            'jumlah_anggaran' => -5000,
        ])->assertStatus(422)->assertJsonValidationErrors(['jumlah_anggaran']);
    }

    // ------------------------------------------------------------------
    // Stunting
    // ------------------------------------------------------------------

    public function test_prevalensi_stunting_dihitung_otomatis(): void
    {
        StuntingRecord::create([
            'village_id' => $this->village->id,
            'periode' => '2026-01',
            'jumlah_balita_diukur' => 200,
            'jumlah_kasus_stunting' => 25,
        ]);

        $this->getJson('/api/v1/infografis/stunting')
            ->assertOk()
            ->assertJsonPath('data.ringkasan.persentase_prevalensi', 12.5);
    }

    /** Tanpa aturan ini, prevalensi bisa tampil melebihi 100%. */
    public function test_kasus_stunting_melebihi_balita_diukur_ditolak(): void
    {
        Sanctum::actingAs($this->operator());

        $this->postJson('/api/v1/admin/stunting', [
            'periode' => '2026-01',
            'jumlah_balita_diukur' => 100,
            'jumlah_kasus_stunting' => 150,
        ])->assertStatus(422)->assertJsonValidationErrors(['jumlah_kasus_stunting']);
    }

    public function test_prevalensi_null_saat_belum_ada_balita_diukur(): void
    {
        StuntingRecord::create([
            'village_id' => $this->village->id,
            'periode' => '2026-01',
            'jumlah_balita_diukur' => 0,
            'jumlah_kasus_stunting' => 0,
        ]);

        // Pembagian dengan nol tidak boleh terjadi; nilainya null, bukan galat.
        $this->getJson('/api/v1/infografis/stunting')
            ->assertOk()
            ->assertJsonPath('data.ringkasan.persentase_prevalensi', null);
    }

    /** Data stunting publik WAJIB agregat — PRD 6.5. */
    public function test_endpoint_stunting_tidak_memuat_identitas_apa_pun(): void
    {
        StuntingRecord::create([
            'village_id' => $this->village->id,
            'periode' => '2026-01',
            'jumlah_balita_diukur' => 50,
            'jumlah_kasus_stunting' => 5,
        ]);

        $isi = $this->getJson('/api/v1/infografis/stunting')->assertOk()->getContent();

        foreach (['nik', 'nama_balita', 'nama_ibu'] as $kolom) {
            $this->assertStringNotContainsString($kolom, $isi);
        }
    }

    // ------------------------------------------------------------------
    // IDM
    // ------------------------------------------------------------------

    /** IDM = rata-rata IKS, IKE, IKL (rumus Kemendes PDTT). */
    public function test_skor_idm_komposit_dihitung_otomatis(): void
    {
        Sanctum::actingAs($this->operator());

        $this->postJson('/api/v1/admin/idm', [
            'tahun' => 2026,
            'skor_iks' => 0.7500,
            'skor_ike' => 0.6000,
            'skor_ikl' => 0.8000,
            'publikasikan' => true,
        ])->assertOk();

        // (0.75 + 0.60 + 0.80) / 3 = 0.7167
        $this->assertSame(0.7167, IdmScore::first()->skor_idm);
    }

    /**
     * Bila salah satu sub-indeks belum diisi, komposit tidak boleh dikarang —
     * angka yang tampak sah padahal datanya belum lengkap lebih berbahaya
     * daripada nilai kosong.
     */
    public function test_komposit_tidak_dihitung_bila_sub_indeks_belum_lengkap(): void
    {
        Sanctum::actingAs($this->operator());

        $this->postJson('/api/v1/admin/idm', [
            'tahun' => 2026,
            'skor_iks' => 0.75,
            'skor_ike' => 0.60,
            // IKL sengaja dikosongkan
        ])->assertOk();

        $this->assertNull(IdmScore::first()->skor_idm);
    }

    public function test_skor_idm_di_luar_rentang_ditolak(): void
    {
        Sanctum::actingAs($this->operator());

        $this->postJson('/api/v1/admin/idm', [
            'tahun' => 2026,
            'skor_iks' => 1.5,      // di luar 0–1
        ])->assertStatus(422)->assertJsonValidationErrors(['skor_iks']);
    }

    public function test_idm_belum_dipublikasikan_tidak_tampil_publik(): void
    {
        IdmScore::create([
            'village_id' => $this->village->id,
            'tahun' => 2026,
            'skor_idm' => 0.7,
            'publikasikan' => false,
        ]);

        $this->getJson('/api/v1/infografis/idm')->assertOk()->assertJsonPath('data', null);
    }

    // ------------------------------------------------------------------
    // SDGs Desa
    // ------------------------------------------------------------------

    /** SDGs Desa terdiri dari 18 tujuan (17 global + 1 khusus desa). */
    public function test_goal_di_luar_rentang_1_sampai_18_ditolak(): void
    {
        Sanctum::actingAs($this->operator());

        $this->postJson('/api/v1/admin/sdgs', [
            'tahun' => 2026,
            'goals' => [['goal_number' => 19, 'nama_goal' => 'Tujuan Tidak Ada']],
        ])->assertStatus(422)->assertJsonValidationErrors(['goals.0.goal_number']);
    }

    public function test_sdgs_menghitung_skor_rata_rata(): void
    {
        // Dipilih agar rata-ratanya tidak bulat (71,67) sehingga pembulatan
        // dua angka desimal ikut teruji.
        foreach ([['1', 80], ['2', 60], ['3', 75]] as [$no, $skor]) {
            SdgsScore::create([
                'village_id' => $this->village->id,
                'tahun' => 2026,
                'goal_number' => (int) $no,
                'nama_goal' => "Tujuan {$no}",
                'skor' => $skor,
                'publikasikan' => true,
            ]);
        }

        $this->getJson('/api/v1/infografis/sdgs')
            ->assertOk()
            ->assertJsonPath('data.skor_rata_rata', 71.67)
            ->assertJsonCount(3, 'data.goals');
    }

    // ------------------------------------------------------------------
    // Batas akses
    // ------------------------------------------------------------------

    public function test_operator_konten_tidak_dapat_mengubah_data_transparansi(): void
    {
        Sanctum::actingAs($this->operator('Operator Konten'));

        $this->postJson('/api/v1/admin/apbdes/tahun', ['tahun' => 2026])->assertStatus(403);
        $this->postJson('/api/v1/admin/idm', ['tahun' => 2026])->assertStatus(403);
        $this->postJson('/api/v1/admin/sdgs', ['tahun' => 2026, 'goals' => []])->assertStatus(403);
        $this->postJson('/api/v1/admin/stunting', [])->assertStatus(403);
    }
}
