<?php

namespace Tests\Feature\Fase4;

use App\Models\PpidInformationItem;
use App\Models\PpidRequest;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Modul PPID — PRD 6.14 & 10.4 (UU No. 14/2008 tentang Keterbukaan
 * Informasi Publik).
 */
class PpidTest extends TestCase
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

        RateLimiter::clear('ajukan-ppid');
    }

    private function operator(string $role = 'Operator PPID'): User
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

    private function ajukan(array $ganti = [])
    {
        return $this->postJson('/api/v1/ppid/permintaan', array_merge([
            'nama_pemohon' => 'Budi Santoso',
            'no_identitas' => '7210010126001234',
            'kontak' => '081234567890',
            'informasi_diminta' => 'Salinan laporan realisasi APBDes tahun 2025.',
            'cara_memperoleh' => 'email',
        ], $ganti));
    }

    // ------------------------------------------------------------------
    // Pengajuan & pelacakan tanpa login
    // ------------------------------------------------------------------

    public function test_warga_dapat_mengajukan_permohonan_tanpa_login(): void
    {
        $response = $this->ajukan()->assertCreated();

        $nomor = $response->json('data.nomor_registrasi');

        $this->assertMatchesRegularExpression('/^PPID-\d{8}-\d{4}$/', $nomor);
        $this->assertSame('diajukan', $response->json('data.status'));
        $this->assertDatabaseCount('ppid_requests', 1);
    }

    public function test_pemohon_dapat_melacak_status_tanpa_login(): void
    {
        $nomor = $this->ajukan()->json('data.nomor_registrasi');

        $this->getJson("/api/v1/ppid/permintaan/{$nomor}/status")
            ->assertOk()
            ->assertJsonPath('data.nomor_registrasi', $nomor)
            ->assertJsonPath('data.status', 'diajukan')
            ->assertJsonPath('data.nama_pemohon', 'Budi Santoso');
    }

    public function test_nomor_registrasi_tidak_dikenal_mengembalikan_404(): void
    {
        $this->getJson('/api/v1/ppid/permintaan/PPID-20260101-0000/status')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    /**
     * Nomor registrasi memuat bagian acak, bukan nomor urut — supaya seseorang
     * tidak dapat menebak nomor milik pemohon lain lalu membaca permohonannya.
     */
    public function test_nomor_registrasi_tidak_berurutan(): void
    {
        $nomor = collect(range(1, 5))
            ->map(fn () => $this->ajukan()->json('data.nomor_registrasi'))
            ->map(fn ($n) => (int) substr($n, -4));

        // Deret berurutan sempurna menandakan nomor dapat ditebak.
        $berurutan = $nomor->sliding(2)->every(fn ($p) => $p[1] - $p[0] === 1);

        $this->assertFalse($berurutan, 'Nomor registrasi tidak boleh berurutan.');
    }

    // ------------------------------------------------------------------
    // Perlindungan data pemohon
    // ------------------------------------------------------------------

    public function test_nomor_identitas_pemohon_tersimpan_terenkripsi(): void
    {
        $this->ajukan()->assertCreated();

        $baris = DB::table('ppid_requests')->first();

        $this->assertStringNotContainsString('7210010126001234', $baris->no_identitas);
        $this->assertNotNull($baris->no_identitas_hash);
    }

    /** Halaman pelacakan publik tidak boleh membocorkan nomor identitas. */
    public function test_pelacakan_publik_tidak_mengembalikan_nomor_identitas(): void
    {
        $nomor = $this->ajukan()->json('data.nomor_registrasi');

        $isi = $this->getJson("/api/v1/ppid/permintaan/{$nomor}/status")
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('7210010126001234', $isi);
        $this->assertStringNotContainsString('no_identitas', $isi);
    }

    // ------------------------------------------------------------------
    // Alur tanggapan (UU KIP)
    // ------------------------------------------------------------------

    public function test_admin_dapat_menanggapi_dan_pemohon_melihat_perubahannya(): void
    {
        $nomor = $this->ajukan()->json('data.nomor_registrasi');
        $permohonan = PpidRequest::where('nomor_registrasi', $nomor)->first();

        Sanctum::actingAs($this->operator());

        $this->putJson("/api/v1/admin/ppid/permohonan/{$permohonan->id}", [
            'status' => 'selesai',
            'tanggapan_admin' => 'Dokumen dikirim ke email pemohon.',
        ])->assertOk();

        $this->getJson("/api/v1/ppid/permintaan/{$nomor}/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.tanggapan_admin', 'Dokumen dikirim ke email pemohon.');
    }

    /**
     * UU KIP mewajibkan penolakan disertai alasan — pemohon berhak
     * mengetahui dasarnya untuk dapat mengajukan keberatan.
     */
    public function test_penolakan_tanpa_alasan_ditolak_sistem(): void
    {
        $nomor = $this->ajukan()->json('data.nomor_registrasi');
        $permohonan = PpidRequest::where('nomor_registrasi', $nomor)->first();

        Sanctum::actingAs($this->operator());

        $this->putJson("/api/v1/admin/ppid/permohonan/{$permohonan->id}", [
            'status' => 'ditolak',
        ])->assertStatus(422)->assertJsonValidationErrors(['alasan_penolakan']);

        // Dengan alasan, penolakan diterima.
        $this->putJson("/api/v1/admin/ppid/permohonan/{$permohonan->id}", [
            'status' => 'ditolak',
            'alasan_penolakan' => 'Informasi yang diminta dikecualikan menurut Pasal 17 UU KIP.',
        ])->assertOk();

        $this->getJson("/api/v1/ppid/permintaan/{$nomor}/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'ditolak')
            ->assertJsonPath(
                'data.alasan_penolakan',
                'Informasi yang diminta dikecualikan menurut Pasal 17 UU KIP.'
            );
    }

    public function test_membuka_permohonan_tercatat_pada_audit_trail(): void
    {
        $nomor = $this->ajukan()->json('data.nomor_registrasi');
        $permohonan = PpidRequest::where('nomor_registrasi', $nomor)->first();

        $operator = $this->operator();
        Sanctum::actingAs($operator);

        $this->getJson("/api/v1/admin/ppid/permohonan/{$permohonan->id}")->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'aksi' => 'viewed',
            'user_id' => $operator->id,
            'subjek_id' => $permohonan->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Informasi publik
    // ------------------------------------------------------------------

    public function test_informasi_disaring_menurut_jenisnya(): void
    {
        foreach (['berkala', 'serta-merta', 'setiap-saat'] as $jenis) {
            PpidInformationItem::create([
                'village_id' => $this->village->id,
                'jenis' => $jenis,
                'judul' => "Dokumen {$jenis}",
                'tanggal_publish' => now()->toDateString(),
            ]);
        }

        $hasil = $this->getJson('/api/v1/ppid/informasi?jenis=berkala')->assertOk()->json('data');

        $this->assertCount(1, $hasil);
        $this->assertSame('Dokumen berkala', $hasil[0]['judul']);
    }

    public function test_jenis_informasi_di_luar_daftar_ditolak(): void
    {
        $this->getJson('/api/v1/ppid/informasi?jenis=rahasia')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['jenis']);
    }

    public function test_informasi_yang_disembunyikan_tidak_tampil_publik(): void
    {
        PpidInformationItem::create([
            'village_id' => $this->village->id,
            'jenis' => 'berkala',
            'judul' => 'Draf Belum Siap',
            'status_tampil' => false,
            'tanggal_publish' => now()->toDateString(),
        ]);

        $this->getJson('/api/v1/ppid/informasi?jenis=berkala')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ------------------------------------------------------------------
    // Batas akses
    // ------------------------------------------------------------------

    public function test_operator_konten_tidak_dapat_menanggapi_permohonan(): void
    {
        $nomor = $this->ajukan()->json('data.nomor_registrasi');
        $permohonan = PpidRequest::where('nomor_registrasi', $nomor)->first();

        Sanctum::actingAs($this->operator('Operator Konten'));

        $this->putJson("/api/v1/admin/ppid/permohonan/{$permohonan->id}", [
            'status' => 'selesai',
        ])->assertStatus(403);
    }

    public function test_operator_ppid_tidak_dapat_mengakses_data_bansos(): void
    {
        Sanctum::actingAs($this->operator('Operator PPID'));

        $this->getJson('/api/v1/admin/bansos/penerima')->assertStatus(403);
    }
}
