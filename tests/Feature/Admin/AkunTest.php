<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Akun sendiri — data diri & kata sandi operator yang sedang masuk.
 *
 * Dua hal yang dijaga di sini: layar ini terbuka bagi SETIAP operator (tanpa
 * `manage-users`, yang justru ditahan dari sebagian besar peran), dan ia tidak
 * dapat dipakai menaikkan peran sendiri.
 */
class AkunTest extends TestCase
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
            'name' => 'Operator Konten',
            'email' => 'konten@uji.test',
            'password' => Hash::make('rahasia123'),
            'village_id' => $this->desa->id,
            'status_aktif' => true,
        ]);
        $this->operator->assignRole('Operator Konten');
    }

    public function test_operator_tanpa_izin_kelola_akun_tetap_dapat_membuka_halamannya(): void
    {
        $this->assertFalse($this->operator->can('manage-users'));

        $this->actingAs($this->operator)->get('/admin/akun')->assertOk();
    }

    public function test_tamu_dialihkan_ke_halaman_masuk(): void
    {
        $this->get('/admin/akun')->assertRedirect('/admin/masuk');
    }

    public function test_data_diri_dapat_diperbarui(): void
    {
        $this->actingAs($this->operator)
            ->put('/admin/akun', [
                'nama' => 'Nama Baru',
                'email' => 'baru@uji.test',
                'no_telepon' => '08987654321',
            ])
            ->assertRedirect();

        $this->operator->refresh();

        $this->assertSame('Nama Baru', $this->operator->name);
        $this->assertSame('baru@uji.test', $this->operator->email);
        $this->assertDatabaseHas('activity_logs', ['aksi' => 'akun_diubah']);
    }

    public function test_email_milik_operator_lain_ditolak(): void
    {
        User::create([
            'name' => 'Operator Lain',
            'email' => 'lain@uji.test',
            'password' => Hash::make('rahasia123'),
            'village_id' => $this->desa->id,
        ]);

        $this->actingAs($this->operator)
            ->put('/admin/akun', ['nama' => 'Nama', 'email' => 'lain@uji.test'])
            ->assertSessionHasErrors('email');

        $this->assertSame('konten@uji.test', $this->operator->fresh()->email);
    }

    public function test_peran_dan_status_tidak_dapat_diubah_dari_layar_ini(): void
    {
        $this->actingAs($this->operator)->put('/admin/akun', [
            'nama' => 'Nama',
            'email' => 'konten@uji.test',
            // Dikirim menumpang, seolah-olah layar ini menerimanya.
            'peran' => 'Admin Utama',
            'status_aktif' => false,
            'village_id' => 999,
        ])->assertRedirect();

        $this->operator->refresh();

        $this->assertTrue($this->operator->hasRole('Operator Konten'));
        $this->assertFalse($this->operator->hasRole('Admin Utama'));
        $this->assertTrue($this->operator->status_aktif);
        $this->assertSame($this->desa->id, $this->operator->village_id);
    }

    public function test_kata_sandi_dapat_diganti(): void
    {
        $this->actingAs($this->operator)
            ->put('/admin/akun/sandi', [
                'password_lama' => 'rahasia123',
                'password' => 'sandibaru123',
                'password_confirmation' => 'sandibaru123',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('sandibaru123', $this->operator->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['aksi' => 'akun_sandi_diubah']);
    }

    public function test_kata_sandi_lama_yang_salah_ditolak_dan_tercatat(): void
    {
        $this->actingAs($this->operator)
            ->put('/admin/akun/sandi', [
                'password_lama' => 'tebakan-salah',
                'password' => 'sandibaru123',
                'password_confirmation' => 'sandibaru123',
            ])
            ->assertSessionHasErrors('password_lama');

        $this->assertTrue(Hash::check('rahasia123', $this->operator->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['aksi' => 'akun_sandi_gagal']);
    }

    public function test_kata_sandi_baru_yang_lemah_atau_tak_cocok_ditolak(): void
    {
        $this->actingAs($this->operator)
            ->put('/admin/akun/sandi', [
                'password_lama' => 'rahasia123',
                'password' => 'abc',
                'password_confirmation' => 'abc',
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($this->operator)
            ->put('/admin/akun/sandi', [
                'password_lama' => 'rahasia123',
                'password' => 'sandibaru123',
                'password_confirmation' => 'sandilain123',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('rahasia123', $this->operator->fresh()->password));
    }

    public function test_audit_log_ganti_sandi_tidak_memuat_kata_sandi(): void
    {
        $this->actingAs($this->operator)->put('/admin/akun/sandi', [
            'password_lama' => 'rahasia123',
            'password' => 'sandibaru123',
            'password_confirmation' => 'sandibaru123',
        ]);

        $log = \App\Models\ActivityLog::where('aksi', 'akun_sandi_diubah')->firstOrFail();

        $isi = json_encode([$log->deskripsi, $log->data_sebelum, $log->data_sesudah]);

        $this->assertStringNotContainsString('sandibaru123', $isi);
        $this->assertStringNotContainsString('rahasia123', $isi);
    }
}
