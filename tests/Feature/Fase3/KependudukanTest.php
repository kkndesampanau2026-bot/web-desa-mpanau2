<?php

namespace Tests\Feature\Fase3;

use App\Models\Dusun;
use App\Models\Resident;
use App\Models\User;
use App\Models\Village;
use App\Services\PopulationAggregator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Modul Kependudukan: agregasi, impor CSV, dan batas akses — PRD 6.3 & 12.2.
 */
class KependudukanTest extends TestCase
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

    private function buatPenduduk(array $atribut = []): Resident
    {
        static $urutan = 0;
        $urutan++;

        return Resident::create(array_merge([
            'village_id' => $this->village->id,
            'nik' => str_pad((string) (7210010126000000 + $urutan), 16, '0', STR_PAD_LEFT),
            'no_kk' => '7210010126009999',
            'nama' => "Warga {$urutan}",
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '1990-01-01',
        ], $atribut));
    }

    // ------------------------------------------------------------------
    // Agregasi
    // ------------------------------------------------------------------

    public function test_snapshot_menghitung_ringkasan_dengan_benar(): void
    {
        $this->buatPenduduk(['jenis_kelamin' => 'L', 'no_kk' => '7210010126001111']);
        $this->buatPenduduk(['jenis_kelamin' => 'P', 'no_kk' => '7210010126001111']);
        $this->buatPenduduk(['jenis_kelamin' => 'P', 'no_kk' => '7210010126002222']);

        $snapshot = app(PopulationAggregator::class)->buatSnapshot($this->village->id);

        $this->assertSame(3, $snapshot->total_penduduk);
        $this->assertSame(1, $snapshot->total_laki);
        $this->assertSame(2, $snapshot->total_perempuan);
        // Dua orang berbagi satu KK, jadi jumlah KK unik ada 2 — dihitung dari
        // blind index tanpa mendekripsi satu pun nomor KK.
        $this->assertSame(2, $snapshot->total_kk);
    }

    public function test_snapshot_mengelompokkan_penduduk_menurut_umur(): void
    {
        $this->buatPenduduk(['tanggal_lahir' => now()->subYears(3)->toDateString()]);   // balita
        $this->buatPenduduk(['tanggal_lahir' => now()->subYears(8)->toDateString()]);   // anak
        $this->buatPenduduk(['tanggal_lahir' => now()->subYears(30)->toDateString()]);  // dewasa
        $this->buatPenduduk(['tanggal_lahir' => now()->subYears(70)->toDateString()]);  // lansia

        $umur = collect(
            app(PopulationAggregator::class)->buatSnapshot($this->village->id)
                ->breakdown['kelompok_umur']
        )->pluck('jumlah', 'label');

        $this->assertSame(1, $umur['Balita (0-4)']);
        $this->assertSame(1, $umur['Anak (5-12)']);
        $this->assertSame(1, $umur['Dewasa (18-59)']);
        $this->assertSame(1, $umur['Lansia (60+)']);
    }

    /**
     * Penduduk tanpa dusun harus tetap terhitung; bila hilang diam-diam,
     * total per dusun tidak akan cocok dengan total penduduk.
     */
    public function test_penduduk_tanpa_dusun_tetap_masuk_hitungan(): void
    {
        $dusun = Dusun::create(['village_id' => $this->village->id, 'nama' => 'Dusun 1']);

        $this->buatPenduduk(['dusun_id' => $dusun->id]);
        $this->buatPenduduk(['dusun_id' => null]);

        $perDusun = collect(
            app(PopulationAggregator::class)->buatSnapshot($this->village->id)
                ->breakdown['dusun']
        )->pluck('jumlah', 'label');

        $this->assertSame(1, $perDusun['Dusun 1']);
        $this->assertSame(1, $perDusun['Belum Ditentukan']);
        $this->assertSame(2, $perDusun->sum());
    }

    // ------------------------------------------------------------------
    // Endpoint publik
    // ------------------------------------------------------------------

    public function test_infografis_penduduk_publik_menyajikan_agregat_saja(): void
    {
        $this->buatPenduduk(['nama' => 'Budi Santoso', 'nik' => '7210010126007777']);
        app(PopulationAggregator::class)->buatSnapshot($this->village->id);

        $response = $this->getJson('/api/v1/infografis/penduduk')->assertOk();

        $response->assertJsonPath('data.ringkasan.total_penduduk', 1);

        // Tidak boleh ada jejak data pribadi pada respons publik.
        $isi = $response->getContent();
        $this->assertStringNotContainsString('7210010126007777', $isi);
        $this->assertStringNotContainsString('Budi Santoso', $isi);
    }

    public function test_infografis_penduduk_menampilkan_empty_state_saat_belum_ada_snapshot(): void
    {
        $this->getJson('/api/v1/infografis/penduduk')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    // ------------------------------------------------------------------
    // Batas akses (PRD 12.2)
    // ------------------------------------------------------------------

    public function test_daftar_penduduk_admin_hanya_menampilkan_nik_tersamar(): void
    {
        Sanctum::actingAs($this->operator());
        $this->buatPenduduk(['nik' => '7210010126008888']);

        $response = $this->getJson('/api/v1/admin/residents')->assertOk();

        $this->assertStringNotContainsString('7210010126008888', $response->getContent());
        $response->assertJsonPath('data.0.nik', '721001**********');
    }

    /** Membuka NIK utuh menuntut permission tersendiri. */
    public function test_melihat_nik_utuh_menuntut_permission_terpisah(): void
    {
        $penduduk = $this->buatPenduduk(['nik' => '7210010126005555']);

        // Operator Konten tidak punya akses sama sekali ke modul ini.
        Sanctum::actingAs($this->operator('Operator Konten'));
        $this->getJson("/api/v1/admin/residents/{$penduduk->id}")->assertStatus(403);

        // Admin Utama memegang view-population-pii.
        Sanctum::actingAs($this->operator('Admin Utama'));
        $this->getJson("/api/v1/admin/residents/{$penduduk->id}")
            ->assertOk()
            ->assertJsonPath('data.nik', '7210010126005555');
    }

    /** UU PDP menuntut pembacaan data pribadi ikut tercatat, bukan hanya perubahan. */
    public function test_membuka_nik_utuh_tercatat_pada_audit_trail(): void
    {
        $operator = $this->operator();
        Sanctum::actingAs($operator);
        $penduduk = $this->buatPenduduk();

        $this->getJson("/api/v1/admin/residents/{$penduduk->id}")->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'aksi' => 'viewed',
            'user_id' => $operator->id,
            'subjek_id' => $penduduk->id,
        ]);
    }

    public function test_audit_trail_tidak_pernah_menyimpan_nik(): void
    {
        Sanctum::actingAs($this->operator());

        $this->postJson('/api/v1/admin/residents', [
            'nik' => '7210010126004444',
            'nama' => 'Warga Baru',
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '1995-03-10',
        ])->assertCreated();

        $log = \App\Models\ActivityLog::where('aksi', 'created')->latest('id')->first();

        $this->assertStringNotContainsString('7210010126004444', json_encode($log->toArray()));
    }

    public function test_operator_konten_tidak_dapat_mengakses_data_penduduk(): void
    {
        Sanctum::actingAs($this->operator('Operator Konten'));

        $this->getJson('/api/v1/admin/residents')->assertStatus(403);
        $this->postJson('/api/v1/admin/residents', [])->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Impor CSV (PRD 10.5)
    // ------------------------------------------------------------------

    private function unggahCsv(string $isi)
    {
        return $this->post('/api/v1/admin/residents/import', [
            'file' => UploadedFile::fake()->createWithContent('penduduk.csv', $isi),
        ], ['Accept' => 'application/json']);
    }

    public function test_impor_csv_menyimpan_baris_valid(): void
    {
        Sanctum::actingAs($this->operator());
        Dusun::create(['village_id' => $this->village->id, 'nama' => 'Dusun 1']);

        $csv = "nik,nama,jenis_kelamin,tanggal_lahir,dusun\n"
            ."7210010126000101,Budi Santoso,L,1990-05-17,Dusun 1\n"
            ."7210010126000102,Siti Aminah,P,1992-08-20,Dusun 1\n";

        $this->unggahCsv($csv)
            ->assertOk()
            ->assertJsonPath('data.berhasil', 2)
            ->assertJsonPath('data.gagal', 0);

        $this->assertSame(2, Resident::count());
        $this->assertSame('Budi Santoso', Resident::cariNik('7210010126000101')->first()->nama);
    }

    /**
     * Inti rancangan impor: baris yang salah tidak boleh menjatuhkan baris
     * yang benar (PRD 10.5).
     */
    public function test_baris_bermasalah_tidak_menggagalkan_baris_lain(): void
    {
        Sanctum::actingAs($this->operator());

        // 2 baris sah diapit 3 baris bermasalah, masing-masing dengan jenis
        // kesalahan berbeda.
        $csv = "nik,nama,jenis_kelamin\n"
            ."7210010126000201,Warga Sah Pertama,L\n"         // baris 2 — sah
            ."123,NIK Terlalu Pendek,L\n"                     // baris 3 — NIK bukan 16 digit
            .",Tanpa NIK,P\n"                                 // baris 4 — NIK kosong
            ."7210010126000204,Jenis Kelamin Salah,X\n"       // baris 5 — bukan L/P
            ."7210010126000205,Warga Sah Kedua,P\n";          // baris 6 — sah

        $response = $this->unggahCsv($csv)->assertOk();

        $this->assertSame(2, $response->json('data.berhasil'));
        $this->assertSame(3, $response->json('data.gagal'));
        $this->assertSame(2, Resident::count());

        // Ringkasan menyebut nomor baris & alasannya, sesuai PRD 10.5.
        $galat = collect($response->json('data.galat'));
        $this->assertSame([3, 4, 5], $galat->pluck('baris')->all());
        $this->assertStringContainsString('16 digit', $galat[0]['pesan']);
        $this->assertStringContainsString('L atau P', $galat[2]['pesan']);
    }

    public function test_nik_ganda_dalam_satu_berkas_terdeteksi(): void
    {
        Sanctum::actingAs($this->operator());

        $csv = "nik,nama,jenis_kelamin\n"
            ."7210010126000301,Warga Pertama,L\n"
            ."7210010126000301,Warga Kembar,P\n";

        $response = $this->unggahCsv($csv)->assertOk();

        $this->assertSame(1, $response->json('data.berhasil'));
        $this->assertStringContainsString('ganda', $response->json('data.galat.0.pesan'));
    }

    /** Mengunggah ulang berkas yang sama memperbarui, bukan menggandakan. */
    public function test_impor_ulang_memperbarui_data_yang_sudah_ada(): void
    {
        Sanctum::actingAs($this->operator());

        $this->unggahCsv("nik,nama,jenis_kelamin\n7210010126000401,Nama Lama,L\n")->assertOk();
        $this->unggahCsv("nik,nama,jenis_kelamin\n7210010126000401,Nama Baru,L\n")->assertOk();

        $this->assertSame(1, Resident::count());
        $this->assertSame('Nama Baru', Resident::first()->nama);
    }

    public function test_csv_tanpa_kolom_wajib_ditolak(): void
    {
        Sanctum::actingAs($this->operator());

        $this->unggahCsv("nama,alamat\nBudi,Jalan Mawar\n")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_nik_tersamar_pada_ringkasan_galat_impor(): void
    {
        Sanctum::actingAs($this->operator());

        // NIK 15 digit — gagal validasi, namun tetap tidak boleh terpampang utuh
        // pada laporan hasil impor.
        $response = $this->unggahCsv("nik,nama,jenis_kelamin\n721001012600050,Warga,L\n")->assertOk();

        $this->assertStringNotContainsString('721001012600050', $response->getContent());
    }

    /** Route statis tidak boleh tertelan oleh route berparameter. */
    public function test_unduhan_template_csv_tidak_tertukar_dengan_detail_penduduk(): void
    {
        Sanctum::actingAs($this->operator());

        $response = $this->get('/api/v1/admin/residents/template-csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('nik,nama,jenis_kelamin', $response->streamedContent());
    }

    public function test_impor_memperbarui_snapshot_agregat_secara_otomatis(): void
    {
        Sanctum::actingAs($this->operator());

        $this->unggahCsv(
            "nik,nama,jenis_kelamin\n"
            ."7210010126000601,Warga A,L\n"
            ."7210010126000602,Warga B,P\n"
        )->assertOk();

        // Halaman publik langsung mutakhir tanpa menunggu job terjadwal.
        $this->getJson('/api/v1/infografis/penduduk')
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total_penduduk', 2);
    }
}
