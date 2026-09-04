<?php

namespace Tests\Feature\Admin;

use App\Models\BansosType;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetYear;
use App\Models\IdmScore;
use App\Models\PpidInformationItem;
use App\Models\PpidLegalBasis;
use App\Models\SdgsScore;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Operasi ubah & hapus yang sebelumnya tidak dimiliki dashboard.
 *
 * Sebagian modul hanya menyediakan "tambah" dan "hapus": satu salah ketik pada
 * judul dasar hukum menuntut operator menghapus barisnya lalu mengunggah ulang
 * PDF-nya. Yang diuji di sini bukan hanya bahwa endpointnya menjawab 200,
 * melainkan dua hal yang paling mudah keliru: kepemilikan desa ditegakkan, dan
 * penghapusan yang akan meninggalkan data yatim ditolak dengan alasan.
 */
class CrudLengkapTest extends TestCase
{
    use RefreshDatabase;

    private Village $desa;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->desa = Village::create([
            'nama' => 'Desa Uji', 'slug' => 'desa-uji', 'is_active' => true,
        ]);

        $this->operator = User::create([
            'name' => 'Admin Uji',
            'email' => 'admin@uji.test',
            'password' => Hash::make('rahasia'),
            'village_id' => $this->desa->id,
        ]);
        $this->operator->assignRole('Admin Utama');
    }

    public function test_dasar_hukum_ppid_dapat_diubah(): void
    {
        $dasar = PpidLegalBasis::create([
            'village_id' => $this->desa->id,
            'judul_regulasi' => 'Perdes Salah Ketik',
            'file_pdf' => 'ppid/berkas-lama.pdf',
        ]);

        $this->actingAs($this->operator)
            ->putJson("/api/v1/admin/ppid/dasar-hukum/{$dasar->id}", [
                'judul_regulasi' => 'Perdes Keterbukaan Informasi',
                'tahun' => 2026,
            ])
            ->assertOk();

        $dasar->refresh();
        $this->assertSame('Perdes Keterbukaan Informasi', $dasar->judul_regulasi);
        // Menyimpan tanpa memilih berkas tidak boleh menghapus PDF yang
        // tautannya sudah terlanjur dibagikan.
        $this->assertSame('ppid/berkas-lama.pdf', $dasar->file_pdf);
    }

    public function test_informasi_ppid_dapat_diubah(): void
    {
        $item = PpidInformationItem::create([
            'village_id' => $this->desa->id,
            'jenis' => 'berkala',
            'judul' => 'Laporan Slaah Ketik',
            'tanggal_publish' => now()->toDateString(),
        ]);

        $this->actingAs($this->operator)
            ->putJson("/api/v1/admin/ppid/informasi/{$item->id}", [
                'jenis' => 'setiap-saat',
                'judul' => 'Laporan Realisasi APBDes',
            ])
            ->assertOk();

        $item->refresh();
        $this->assertSame('Laporan Realisasi APBDes', $item->judul);
        $this->assertSame('setiap-saat', $item->jenis);
    }

    public function test_dasar_hukum_desa_lain_tidak_dapat_diubah(): void
    {
        $desaLain = Village::create([
            'nama' => 'Desa Lain', 'slug' => 'desa-lain', 'is_active' => true,
        ]);

        $milikOrangLain = PpidLegalBasis::create([
            'village_id' => $desaLain->id,
            'judul_regulasi' => 'Perdes Desa Lain',
        ]);

        $this->actingAs($this->operator)
            ->putJson("/api/v1/admin/ppid/dasar-hukum/{$milikOrangLain->id}", [
                'judul_regulasi' => 'Diubah Diam-diam',
            ])
            ->assertNotFound();

        $this->assertSame('Perdes Desa Lain', $milikOrangLain->fresh()->judul_regulasi);
    }

    public function test_jenis_bantuan_dapat_diubah(): void
    {
        $jenis = BansosType::create([
            'village_id' => $this->desa->id,
            'nama' => 'BLT Dana Desaa',
            'slug' => 'blt-dana-desaa',
        ]);

        $this->actingAs($this->operator)
            ->putJson("/api/v1/admin/bansos/jenis/{$jenis->id}", [
                'nama' => 'BLT Dana Desa',
                'sumber_dana' => 'Dana Desa',
            ])
            ->assertOk();

        $this->assertSame('BLT Dana Desa', $jenis->fresh()->nama);
    }

    public function test_kategori_apbdes_dapat_diubah_dan_dihapus(): void
    {
        $kategori = BudgetCategory::create([
            'village_id' => $this->desa->id,
            'kelompok' => 'Belanja',
            'nama' => 'Belanja Pegawaii',
        ]);

        $this->actingAs($this->operator)
            ->putJson("/api/v1/admin/apbdes/kategori/{$kategori->id}", [
                'kelompok' => 'Belanja',
                'nama' => 'Belanja Pegawai',
            ])
            ->assertOk();

        $this->assertSame('Belanja Pegawai', $kategori->fresh()->nama);

        $this->actingAs($this->operator)
            ->deleteJson("/api/v1/admin/apbdes/kategori/{$kategori->id}")
            ->assertOk();

        $this->assertDatabaseMissing('budget_categories', ['id' => $kategori->id]);
    }

    public function test_kategori_dan_tahun_yang_masih_dipakai_tidak_dapat_dihapus(): void
    {
        $tahun = BudgetYear::create(['village_id' => $this->desa->id, 'tahun' => 2026]);
        $kategori = BudgetCategory::create([
            'village_id' => $this->desa->id, 'kelompok' => 'Belanja', 'nama' => 'Belanja Modal',
        ]);

        BudgetItem::create([
            'village_id' => $this->desa->id,
            'budget_year_id' => $tahun->id,
            'budget_category_id' => $kategori->id,
            'nama_item' => 'Pembangunan Jalan',
            'jumlah_anggaran' => 100000,
        ]);

        // Menghapus berantai akan melenyapkan angka yang menjadi rujukan publik
        // pada halaman transparansi; server harus menolak beserta alasannya.
        $this->actingAs($this->operator)
            ->deleteJson("/api/v1/admin/apbdes/kategori/{$kategori->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('budget_categories', ['id' => $kategori->id]);

        $this->actingAs($this->operator)
            ->deleteJson("/api/v1/admin/apbdes/tahun/{$tahun->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('budget_years', ['id' => $tahun->id]);
    }

    public function test_tahun_anggaran_kosong_dapat_dihapus(): void
    {
        $tahun = BudgetYear::create(['village_id' => $this->desa->id, 'tahun' => 2025]);

        $this->actingAs($this->operator)
            ->deleteJson("/api/v1/admin/apbdes/tahun/{$tahun->id}")
            ->assertOk();

        $this->assertDatabaseMissing('budget_years', ['id' => $tahun->id]);
    }

    public function test_skor_idm_dapat_dihapus(): void
    {
        $skor = IdmScore::create(['village_id' => $this->desa->id, 'tahun' => 2026]);

        $this->actingAs($this->operator)
            ->deleteJson("/api/v1/admin/idm/{$skor->id}")
            ->assertOk();

        $this->assertDatabaseMissing('idm_scores', ['id' => $skor->id]);
    }

    public function test_skor_sdgs_dihapus_per_tahun(): void
    {
        foreach ([1, 2, 3] as $goal) {
            SdgsScore::create([
                'village_id' => $this->desa->id,
                'tahun' => 2026,
                'goal_number' => $goal,
                'nama_goal' => "Tujuan {$goal}",
            ]);
        }

        SdgsScore::create([
            'village_id' => $this->desa->id,
            'tahun' => 2025,
            'goal_number' => 1,
            'nama_goal' => 'Tujuan 1',
        ]);

        $this->actingAs($this->operator)
            ->deleteJson('/api/v1/admin/sdgs/2026')
            ->assertOk();

        $this->assertSame(0, SdgsScore::where('tahun', 2026)->count());
        // Tahun lain tidak boleh ikut terbawa.
        $this->assertSame(1, SdgsScore::where('tahun', 2025)->count());
    }

    // ------------------------------------------------------------------------
    // Kolom ENUM: validator harus menolak lebih dahulu, bukan basis data
    // ------------------------------------------------------------------------

    public function test_pendidikan_di_luar_daftar_ditolak_validator(): void
    {
        // Sebelumnya kolom ini divalidasi sebagai string bebas, sehingga nilai
        // seperti "PAUD" lolos lalu ditolak MySQL — operator melihat galat 500
        // "Data truncated", bukan pesan yang menyebutkan pilihan yang sah.
        $this->actingAs($this->operator)
            ->postJson('/api/v1/admin/residents', [
                'nik' => '7210010101990001',
                'nama' => 'Wicak Yusuf',
                'jenis_kelamin' => 'L',
                'pendidikan_terakhir' => 'PAUD',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pendidikan_terakhir']);

        $this->assertDatabaseCount('residents', 0);
    }

    public function test_agama_di_luar_daftar_ditolak_validator(): void
    {
        $this->actingAs($this->operator)
            ->postJson('/api/v1/admin/residents', [
                'nik' => '7210010101990002',
                'nama' => 'Wicak Yusuf',
                'jenis_kelamin' => 'L',
                'agama' => 'atheis',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['agama']);
    }

    public function test_nilai_enum_yang_sah_tersimpan(): void
    {
        $this->actingAs($this->operator)
            ->postJson('/api/v1/admin/residents', [
                'nik' => '7210010101990003',
                'nama' => 'Wicak Yusuf',
                'jenis_kelamin' => 'L',
                'pendidikan_terakhir' => 'SLTA/Sederajat',
                'agama' => 'Islam',
                'status_domisili' => 'Penduduk Sementara',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('residents', [
            'nama' => 'Wicak Yusuf',
            'pendidikan_terakhir' => 'SLTA/Sederajat',
            'agama' => 'Islam',
        ]);
    }

    public function test_endpoint_opsi_memuat_seluruh_daftar_enum(): void
    {
        $this->actingAs($this->operator)
            ->getJson('/api/v1/admin/residents/opsi')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['dusun', 'hubungan_kk', 'pendidikan', 'perkawinan', 'agama', 'domisili'],
            ])
            ->assertJsonPath('data.pendidikan.0', 'Tidak/Belum Sekolah');
    }

    public function test_nik_ganda_ditolak_validator_bukan_basis_data(): void
    {
        $isi = [
            'nik' => '7210010101990009',
            'nama' => 'Warga Pertama',
            'jenis_kelamin' => 'L',
        ];

        $this->actingAs($this->operator)->postJson('/api/v1/admin/residents', $isi)
            ->assertStatus(201);

        // Tanpa pemeriksaan lewat blind index, baris kedua lolos validasi lalu
        // ditolak indeks unik basis data sebagai galat 500.
        $this->actingAs($this->operator)
            ->postJson('/api/v1/admin/residents', [...$isi, 'nama' => 'Warga Kedua'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nik']);

        $this->assertDatabaseCount('residents', 1);
    }

    public function test_menyunting_warga_tidak_menuduh_nik_sendiri_ganda(): void
    {
        $this->actingAs($this->operator)->postJson('/api/v1/admin/residents', [
            'nik' => '7210010101990010',
            'nama' => 'Nama Salah Ketik',
            'jenis_kelamin' => 'P',
        ])->assertStatus(201);

        $id = \App\Models\Resident::where('village_id', $this->desa->id)->value('id');

        // NIK yang sama dikirim ulang saat menyunting — barisnya sendiri harus
        // dikecualikan dari pemeriksaan keunikan.
        $this->actingAs($this->operator)
            ->putJson("/api/v1/admin/residents/{$id}", [
                'nik' => '7210010101990010',
                'nama' => 'Nama Benar',
                'jenis_kelamin' => 'P',
            ])
            ->assertOk();

        $this->assertDatabaseHas('residents', ['id' => $id, 'nama' => 'Nama Benar']);
    }

    public function test_penerima_bansos_ganda_ditolak_validator(): void
    {
        $jenis = BansosType::create([
            'village_id' => $this->desa->id, 'nama' => 'BLT', 'slug' => 'blt',
        ]);

        $isi = [
            'bansos_type_id' => $jenis->id,
            'nama' => 'Budi Santoso',
            'nik' => '7210010101990020',
            'tahun_anggaran' => 2026,
        ];

        $this->actingAs($this->operator)
            ->postJson('/api/v1/admin/bansos/penerima', $isi)
            ->assertStatus(201);

        // Indeks unik `penerima_unik_per_bantuan` melarang orang yang sama
        // tercatat dua kali untuk bantuan & tahun yang sama.
        $this->actingAs($this->operator)
            ->postJson('/api/v1/admin/bansos/penerima', $isi)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nik']);

        $this->assertDatabaseCount('bansos_recipients', 1);
    }

    public function test_penerima_yang_sama_boleh_menerima_bantuan_lain(): void
    {
        $blt = BansosType::create([
            'village_id' => $this->desa->id, 'nama' => 'BLT', 'slug' => 'blt',
        ]);
        $pkh = BansosType::create([
            'village_id' => $this->desa->id, 'nama' => 'PKH', 'slug' => 'pkh',
        ]);

        foreach ([$blt, $pkh] as $jenis) {
            $this->actingAs($this->operator)
                ->postJson('/api/v1/admin/bansos/penerima', [
                    'bansos_type_id' => $jenis->id,
                    'nama' => 'Budi Santoso',
                    'nik' => '7210010101990021',
                    'tahun_anggaran' => 2026,
                ])
                ->assertStatus(201);
        }

        $this->assertDatabaseCount('bansos_recipients', 2);
    }
}
