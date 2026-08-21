<?php

namespace Tests\Feature\Fase2;

use App\Models\BpdMember;
use App\Models\Official;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Modul Profil Desa, SOTK, dan BPD — PRD 6.1 & 6.2.
 */
class ProfilSotkTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->village = Village::create([
            'nama' => 'Desa Uji',
            'slug' => 'desa-uji',
            'is_active' => true,
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

    /**
     * Pola empty-state PRD 3.2: modul tanpa data mengembalikan 200 dengan
     * data null, bukan 404 — agar frontend tetap merender kerangka halaman
     * beserta pesan "Belum Ada Data".
     */
    public function test_profil_belum_diisi_mengembalikan_200_dengan_data_null(): void
    {
        $this->getJson('/api/v1/profil')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    public function test_admin_menyimpan_profil_lalu_tampil_di_endpoint_publik(): void
    {
        Sanctum::actingAs($this->operator());

        $this->putJson('/api/v1/admin/profil', [
            'nama_kepala_desa' => 'Budi Santoso',
            'visi' => 'Desa mandiri dan sejahtera',
            'misi' => ['Meningkatkan pelayanan publik', 'Mengembangkan ekonomi desa'],
            'luas_desa_m2' => 2_512_500,
            'batas_utara' => 'Desa Sidera',
            'latitude' => -1.0234567,
            'longitude' => 119.9876543,
        ])->assertOk();

        $this->getJson('/api/v1/profil')
            ->assertOk()
            ->assertJsonPath('data.nama_kepala_desa', 'Budi Santoso')
            ->assertJsonPath('data.misi.1', 'Mengembangkan ekonomi desa')
            ->assertJsonPath('data.geografis.batas.utara', 'Desa Sidera')
            // 2.512.500 m² = 251,25 hektar (PRD 6.1 menyebut opsi tampilan hektar).
            ->assertJsonPath('data.geografis.luas_desa_hektar', 251.25);
    }

    public function test_koordinat_di_luar_rentang_ditolak(): void
    {
        Sanctum::actingAs($this->operator());

        $this->putJson('/api/v1/admin/profil', [
            'latitude' => 95,      // > 90
            'longitude' => 200,    // > 180
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_daftar_aparat_publik_hanya_yang_aktif_dan_terurut(): void
    {
        Official::create([
            'village_id' => $this->village->id,
            'nama' => 'Sekretaris Desa', 'jabatan' => 'Sekretaris', 'urutan_tampil' => 2,
        ]);
        Official::create([
            'village_id' => $this->village->id,
            'nama' => 'Kepala Desa', 'jabatan' => 'Kepala Desa', 'urutan_tampil' => 1,
        ]);
        Official::create([
            'village_id' => $this->village->id,
            'nama' => 'Mantan Kaur', 'jabatan' => 'Kaur', 'status_aktif' => false,
        ]);

        $data = $this->getJson('/api/v1/officials')->assertOk()->json('data');

        $this->assertCount(2, $data);
        $this->assertSame('Kepala Desa', $data[0]['nama']);
        $this->assertSame('Sekretaris Desa', $data[1]['nama']);
    }

    /** No. SK pengangkatan adalah dokumen kepegawaian, tidak untuk publik. */
    public function test_no_sk_pengangkatan_tidak_bocor_ke_endpoint_publik(): void
    {
        Official::create([
            'village_id' => $this->village->id,
            'nama' => 'Kepala Desa',
            'jabatan' => 'Kepala Desa',
            'no_sk_pengangkatan' => 'SK/RAHASIA/2026',
        ]);

        $response = $this->getJson('/api/v1/officials')->assertOk();

        $response->assertJsonMissing(['no_sk_pengangkatan' => 'SK/RAHASIA/2026']);
        $this->assertStringNotContainsString('SK/RAHASIA/2026', $response->getContent());
    }

    /** BPD diurutkan menurut hierarki jabatan, bukan urutan input. */
    public function test_anggota_bpd_terurut_menurut_hierarki_jabatan(): void
    {
        foreach (['Anggota', 'Ketua', 'Sekretaris', 'Wakil Ketua'] as $jabatan) {
            BpdMember::create([
                'village_id' => $this->village->id,
                'nama' => "Anggota {$jabatan}",
                'jabatan' => $jabatan,
            ]);
        }

        $data = $this->getJson('/api/v1/bpd-members')->assertOk()->json('data');

        $this->assertSame(
            ['Ketua', 'Wakil Ketua', 'Sekretaris', 'Anggota'],
            array_column($data, 'jabatan')
        );
    }

    public function test_jabatan_bpd_di_luar_daftar_resmi_ditolak(): void
    {
        Sanctum::actingAs($this->operator());

        $this->postJson('/api/v1/admin/bpd-members', [
            'nama' => 'Seseorang',
            'jabatan' => 'Bendahara',   // bukan jabatan BPD
        ])->assertStatus(422)->assertJsonValidationErrors(['jabatan']);
    }

    public function test_operator_konten_tidak_boleh_menyunting_profil_desa(): void
    {
        Sanctum::actingAs($this->operator('Operator Konten'));

        $this->putJson('/api/v1/admin/profil', ['visi' => 'Percobaan'])
            ->assertStatus(403);
    }

    public function test_perubahan_profil_tercatat_pada_audit_trail(): void
    {
        $operator = $this->operator();
        Sanctum::actingAs($operator);

        $this->putJson('/api/v1/admin/profil', ['visi' => 'Visi baru'])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $operator->id,
            'deskripsi' => 'Memperbarui profil desa',
        ]);
    }
}
