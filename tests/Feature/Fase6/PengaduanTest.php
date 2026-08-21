<?php

namespace Tests\Feature\Fase6;

use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\User;
use App\Models\Village;
use App\Services\LampiranPengaduanService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pengaduan Masyarakat — PRD 6.15 & 10.2.
 *
 * Perhatian khusus pada unggahan lampiran: ini satu-satunya jalur di seluruh
 * sistem yang menerima berkas dari publik tanpa autentikasi.
 */
class PengaduanTest extends TestCase
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

        Storage::fake(LampiranPengaduanService::DISK);
        RateLimiter::clear('pengaduan');
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

    private function ajukan(array $ganti = [], array $berkas = [])
    {
        return $this->post('/api/v1/pengaduan', array_merge([
            'nama' => 'Budi Santoso',
            'no_telepon_wa' => '081234567890',
            'kategori_pengaduan' => 'Kebersihan',
            'isi_pengaduan' => 'Sampah menumpuk di sekitar jalan dusun sudah dua minggu.',
        ], $ganti, $berkas), ['Accept' => 'application/json']);
    }

    // ------------------------------------------------------------------
    // Pengajuan & pelacakan
    // ------------------------------------------------------------------

    public function test_warga_dapat_mengajukan_pengaduan_tanpa_login(): void
    {
        $response = $this->ajukan()->assertCreated();

        $nomor = $response->json('data.nomor_tiket');

        // Format PGD-YYYYMMDD-XXXX (PRD 6.15).
        $this->assertMatchesRegularExpression('/^PGD-\d{8}-\d{4}$/', $nomor);
        $this->assertSame('baru', $response->json('data.status'));
        $this->assertDatabaseCount('complaints', 1);
    }

    public function test_pelapor_dapat_melacak_status_dengan_nomor_tiket(): void
    {
        $nomor = $this->ajukan()->json('data.nomor_tiket');

        $this->getJson("/api/v1/pengaduan/{$nomor}/status")
            ->assertOk()
            ->assertJsonPath('data.nomor_tiket', $nomor)
            ->assertJsonPath('data.status', 'baru')
            ->assertJsonPath('data.kategori_pengaduan', 'Kebersihan');
    }

    /** Nomor tiket adalah satu-satunya kunci akses, jadi tidak boleh ditebak. */
    public function test_nomor_tiket_tidak_berurutan(): void
    {
        $nomor = collect(range(1, 5))
            ->map(fn () => $this->ajukan()->json('data.nomor_tiket'))
            ->map(fn ($n) => (int) substr($n, -4));

        $berurutan = $nomor->sliding(2)->every(fn ($p) => $p[1] - $p[0] === 1);

        $this->assertFalse($berurutan, 'Nomor tiket tidak boleh berurutan.');
    }

    public function test_nomor_tiket_tidak_dikenal_mengembalikan_404(): void
    {
        $this->getJson('/api/v1/pengaduan/PGD-20260101-0000/status')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    /**
     * Nomor tiket bisa saja terbaca orang lain, sehingga halaman pelacakan
     * tidak boleh membocorkan kontak pelapor.
     */
    public function test_pelacakan_publik_tidak_membocorkan_nomor_telepon_pelapor(): void
    {
        $nomor = $this->ajukan()->json('data.nomor_tiket');

        $isi = $this->getJson("/api/v1/pengaduan/{$nomor}/status")->assertOk()->getContent();

        $this->assertStringNotContainsString('081234567890', $isi);
        $this->assertStringNotContainsString('no_telepon', $isi);
    }

    public function test_nama_pelapor_ditampilkan_tersamar_pada_pelacakan(): void
    {
        $nomor = $this->ajukan()->json('data.nomor_tiket');

        $this->getJson("/api/v1/pengaduan/{$nomor}/status")
            ->assertOk()
            ->assertJsonPath('data.nama', 'Budi S******');
    }

    public function test_kategori_di_luar_enam_pilihan_ditolak(): void
    {
        $this->ajukan(['kategori_pengaduan' => 'Kategori Karangan'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kategori_pengaduan']);
    }

    public function test_enam_kategori_sesuai_prd_tersedia(): void
    {
        $this->getJson('/api/v1/pengaduan/kategori')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => null,
                'data' => ['Umum', 'Sosial', 'Keamanan', 'Kesehatan', 'Kebersihan', 'Permintaan'],
            ]);
    }

    public function test_isi_pengaduan_terlalu_pendek_ditolak(): void
    {
        $this->ajukan(['isi_pengaduan' => 'rusak'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['isi_pengaduan']);
    }

    public function test_tidak_ada_endpoint_publik_yang_mendaftar_pengaduan(): void
    {
        $this->ajukan()->assertCreated();

        $this->getJson('/api/v1/pengaduan')->assertStatus(405);
        $this->getJson('/api/v1/pengaduan/semua')->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // Keamanan unggahan lampiran
    // ------------------------------------------------------------------

    public function test_lampiran_gambar_tersimpan_pada_disk_privat(): void
    {
        $response = $this->ajukan([], [
            'lampiran' => [UploadedFile::fake()->image('bukti.jpg')],
        ])->assertCreated();

        $this->assertSame(1, $response->json('data.jumlah_lampiran'));

        $lampiran = ComplaintAttachment::first();

        // Disimpan di disk privat, bukan storage/app/public yang tertaut web.
        Storage::disk(LampiranPengaduanService::DISK)->assertExists($lampiran->path);
        $this->assertStringStartsWith(LampiranPengaduanService::DIREKTORI, $lampiran->path);
    }

    /**
     * Nama berkas dari pelapor tidak boleh dipakai sebagai nama di disk —
     * ia dapat memuat karakter jebakan atau ekstensi ganda.
     */
    public function test_nama_berkas_di_disk_diacak_bukan_nama_dari_pelapor(): void
    {
        $this->ajukan([], [
            'lampiran' => [UploadedFile::fake()->image('../../jahat.jpg')],
        ])->assertCreated();

        $lampiran = ComplaintAttachment::first();

        $this->assertStringNotContainsString('jahat', $lampiran->path);
        $this->assertStringNotContainsString('..', $lampiran->path);
        // UUID + ekstensi yang ditentukan server.
        $this->assertMatchesRegularExpression('/[0-9a-f-]{36}\.jpg$/', $lampiran->path);
    }

    /** Berkas berbahaya ditolak berdasarkan MIME hasil deteksi isi. */
    public function test_berkas_selain_gambar_dan_pdf_ditolak(): void
    {
        foreach ([
            UploadedFile::fake()->createWithContent('skrip.php', '<?php echo "jahat";'),
            UploadedFile::fake()->createWithContent('skrip.html', '<script>alert(1)</script>'),
            UploadedFile::fake()->createWithContent('arsip.zip', 'PK'),
        ] as $berkas) {
            $this->ajukan([], ['lampiran' => [$berkas]])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['lampiran.0']);
        }

        $this->assertDatabaseCount('complaint_attachments', 0);
    }

    /**
     * Ekstensi ganda seperti "foto.jpg.php" tidak boleh lolos hanya karena
     * namanya diawali .jpg.
     */
    public function test_berkas_php_bersamaran_gambar_tetap_ditolak(): void
    {
        $this->ajukan([], [
            'lampiran' => [
                UploadedFile::fake()->createWithContent('foto.jpg.php', '<?php echo "jahat";'),
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('complaint_attachments', 0);
    }

    public function test_lampiran_melebihi_batas_jumlah_ditolak(): void
    {
        $berkas = collect(range(1, LampiranPengaduanService::MAKS_BERKAS + 2))
            ->map(fn ($i) => UploadedFile::fake()->image("foto{$i}.jpg"))
            ->all();

        $this->ajukan([], ['lampiran' => $berkas])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lampiran']);
    }

    public function test_lampiran_terlalu_besar_ditolak(): void
    {
        $this->ajukan([], [
            'lampiran' => [
                UploadedFile::fake()->create(
                    'besar.pdf',
                    LampiranPengaduanService::MAKS_UKURAN_KB + 100,
                    'application/pdf'
                ),
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['lampiran.0']);
    }

    /** Lampiran tidak boleh dapat diambil lewat URL publik. */
    public function test_lampiran_tidak_dapat_diakses_tanpa_autentikasi(): void
    {
        $this->ajukan([], ['lampiran' => [UploadedFile::fake()->image('bukti.jpg')]])
            ->assertCreated();

        $pengaduan = Complaint::first();
        $lampiran = ComplaintAttachment::first();

        $this->getJson("/api/v1/admin/pengaduan/{$pengaduan->id}/lampiran/{$lampiran->id}")
            ->assertStatus(401);
    }

    public function test_admin_berwenang_dapat_mengunduh_lampiran(): void
    {
        $this->ajukan([], ['lampiran' => [UploadedFile::fake()->image('bukti.jpg')]])
            ->assertCreated();

        $pengaduan = Complaint::first();
        $lampiran = ComplaintAttachment::first();

        Sanctum::actingAs($this->operator());

        $response = $this->get("/api/v1/admin/pengaduan/{$pengaduan->id}/lampiran/{$lampiran->id}");

        $response->assertOk();
        // Selalu attachment, tidak pernah dirender inline oleh peramban.
        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('content-disposition')
        );
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
    }

    /** Lampiran milik pengaduan lain tidak boleh terambil dengan menukar id. */
    public function test_lampiran_pengaduan_lain_tidak_dapat_diambil(): void
    {
        $this->ajukan([], ['lampiran' => [UploadedFile::fake()->image('a.jpg')]])->assertCreated();
        $this->ajukan(['nama' => 'Orang Lain'])->assertCreated();

        $lampiran = ComplaintAttachment::first();
        $pengaduanLain = Complaint::where('nama', 'Orang Lain')->first();

        Sanctum::actingAs($this->operator());

        $this->get("/api/v1/admin/pengaduan/{$pengaduanLain->id}/lampiran/{$lampiran->id}")
            ->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // Alur tanggapan admin
    // ------------------------------------------------------------------

    public function test_admin_menanggapi_dan_pelapor_melihat_perubahannya(): void
    {
        $nomor = $this->ajukan()->json('data.nomor_tiket');
        $pengaduan = Complaint::where('nomor_tiket', $nomor)->first();

        Sanctum::actingAs($this->operator());

        $this->putJson("/api/v1/admin/pengaduan/{$pengaduan->id}", [
            'status' => 'selesai',
            'tanggapan_admin' => 'Sampah telah diangkut petugas kebersihan desa.',
        ])->assertOk();

        $this->getJson("/api/v1/pengaduan/{$nomor}/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.tanggapan_admin', 'Sampah telah diangkut petugas kebersihan desa.');
    }

    public function test_penolakan_tanpa_alasan_ditolak_sistem(): void
    {
        $nomor = $this->ajukan()->json('data.nomor_tiket');
        $pengaduan = Complaint::where('nomor_tiket', $nomor)->first();

        Sanctum::actingAs($this->operator());

        $this->putJson("/api/v1/admin/pengaduan/{$pengaduan->id}", ['status' => 'ditolak'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['alasan_penolakan']);
    }

    /** Daftar admin tidak memuat kontak pelapor; kontak baru terbuka di detail. */
    public function test_daftar_admin_tidak_memuat_nomor_telepon(): void
    {
        $this->ajukan()->assertCreated();

        Sanctum::actingAs($this->operator());

        $isi = $this->getJson('/api/v1/admin/pengaduan')->assertOk()->getContent();

        $this->assertStringNotContainsString('081234567890', $isi);
    }

    public function test_membuka_detail_pengaduan_tercatat_pada_audit_trail(): void
    {
        $this->ajukan()->assertCreated();
        $pengaduan = Complaint::first();

        $operator = $this->operator();
        Sanctum::actingAs($operator);

        $this->getJson("/api/v1/admin/pengaduan/{$pengaduan->id}")
            ->assertOk()
            ->assertJsonPath('data.no_telepon_wa', '081234567890');

        $this->assertDatabaseHas('activity_logs', [
            'aksi' => 'viewed',
            'user_id' => $operator->id,
            'subjek_id' => $pengaduan->id,
        ]);
    }

    public function test_operator_ppid_tidak_dapat_menanggapi_pengaduan(): void
    {
        $this->ajukan()->assertCreated();
        $pengaduan = Complaint::first();

        Sanctum::actingAs($this->operator('Operator PPID'));

        $this->putJson("/api/v1/admin/pengaduan/{$pengaduan->id}", ['status' => 'selesai'])
            ->assertStatus(403);
    }
}
