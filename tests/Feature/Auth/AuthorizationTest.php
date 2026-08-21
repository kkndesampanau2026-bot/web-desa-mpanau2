<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menegakkan pemisahan hak akses antar-role sebagaimana PRD 12.2 —
 * khususnya bahwa data sensitif hanya terjangkau Admin Utama.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::create([
            'name' => 'Pengguna '.$role,
            'email' => uniqid().'@desa.test',
            'password' => Hash::make('rahasia123'),
            'status_aktif' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }

    public function test_pengguna_dengan_permission_dapat_mengakses_endpoint_terproteksi(): void
    {
        Sanctum::actingAs($this->user('Admin Utama'));

        $this->getJson('/api/v1/admin/ping')
            ->assertOk()
            ->assertJsonPath('data.pong', true);
    }

    public function test_super_admin_melewati_seluruh_pemeriksaan_permission(): void
    {
        $superAdmin = $this->user('Super Admin');

        // Tidak punya satu pun permission eksplisit — akses berasal dari Gate::before.
        $this->assertCount(0, $superAdmin->getAllPermissions());

        Sanctum::actingAs($superAdmin);
        $this->getJson('/api/v1/admin/ping')->assertOk();
    }

    public function test_operator_konten_tidak_punya_akses_data_sensitif(): void
    {
        $operator = $this->user('Operator Konten');

        $this->assertTrue($operator->can('manage-news'));
        $this->assertFalse($operator->can('manage-population-data'));
        $this->assertFalse($operator->can('view-population-pii'));
        $this->assertFalse($operator->can('manage-bansos'));
        $this->assertFalse($operator->can('manage-apbdes'));
    }

    public function test_operator_ppid_terbatas_pada_modul_layanan(): void
    {
        $operator = $this->user('Operator PPID');

        $this->assertTrue($operator->can('respond-ppid-request'));
        $this->assertTrue($operator->can('manage-ppid-content'));
        $this->assertFalse($operator->can('manage-news'));
        $this->assertFalse($operator->can('manage-population-data'));
        $this->assertFalse($operator->can('manage-users'));
    }

    public function test_admin_utama_menguasai_seluruh_modul_data_sensitif(): void
    {
        $admin = $this->user('Admin Utama');

        foreach (['manage-population-data', 'view-population-pii', 'export-population-data',
            'manage-bansos', 'view-bansos-pii', 'manage-apbdes'] as $izin) {
            $this->assertTrue($admin->can($izin), "Admin Utama seharusnya punya izin: {$izin}");
        }
    }
}
