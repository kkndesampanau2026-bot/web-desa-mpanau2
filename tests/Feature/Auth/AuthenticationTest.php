<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verifikasi fondasi Fase 1: autentikasi Sanctum + otorisasi berbasis
 * permission (PRD 5.1, 5.2, 12.2).
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function buatUser(string $role, bool $aktif = true): User
    {
        $village = Village::create([
            'nama' => 'Desa Uji',
            'slug' => 'desa-uji-'.uniqid(),
        ]);

        $user = User::create([
            'village_id' => $village->id,
            'name' => 'Pengguna Uji',
            'email' => uniqid().'@desa.test',
            'password' => Hash::make('rahasia123'),
            'status_aktif' => $aktif,
        ]);

        $user->assignRole($role);

        return $user;
    }

    public function test_login_berhasil_mengembalikan_profil_beserta_permission(): void
    {
        $user = $this->buatUser('Operator Konten');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'rahasia123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles.0', 'Operator Konten');

        // Operator Konten harus punya izin kelola berita, tetapi tidak boleh
        // menyentuh data kependudukan (PRD 12.2).
        $izin = $response->json('data.permissions');
        $this->assertContains('manage-news', $izin);
        $this->assertNotContains('manage-population-data', $izin);
    }

    public function test_login_gagal_dengan_kata_sandi_salah(): void
    {
        $user = $this->buatUser('Admin Utama');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'kata-sandi-salah',
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertDatabaseHas('activity_logs', ['aksi' => 'failed_login']);
    }

    public function test_akun_nonaktif_ditolak_masuk(): void
    {
        $user = $this->buatUser('Admin Utama', aktif: false);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'rahasia123',
        ])->assertStatus(403);

        $this->assertGuest();
    }

    public function test_endpoint_admin_menolak_tamu(): void
    {
        $this->getJson('/api/v1/admin/ping')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    /**
     * Regresi: permintaan tanpa header `Accept: application/json` sempat
     * menghasilkan 500 karena middleware Authenticate mencoba mengalihkan tamu
     * ke route bernama `login` yang tidak ada pada backend headless ini.
     *
     * Memakai get() — bukan getJson() — justru karena helper JSON menyetel
     * header Accept dan dengan begitu menutupi bug ini.
     */
    public function test_endpoint_admin_mengembalikan_401_meski_tanpa_header_accept_json(): void
    {
        $this->get('/api/v1/admin/ping')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_login_berhasil_dicatat_pada_audit_trail(): void
    {
        $user = $this->buatUser('Admin Utama');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'rahasia123',
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'aksi' => 'login',
            'user_id' => $user->id,
        ]);
    }

    public function test_endpoint_publik_dapat_diakses_tanpa_autentikasi(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version', 'v1');
    }
}
