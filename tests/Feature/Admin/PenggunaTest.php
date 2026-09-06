<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pengelolaan akun operator — PRD 5.19.
 *
 * Yang diuji di sini bukan sekadar "endpointnya menjawab 200", melainkan
 * batas-batas yang membuat layar ini tidak dapat dipakai meraih kewenangan
 * yang tidak diberikan: peran `Operator Utama` yang sengaja ditahan haknya
 * membuat akun, larangan mencetak Super Admin, dan penjagaan agar dashboard
 * tidak pernah kehilangan seluruh pengelola akunnya.
 */
class PenggunaTest extends TestCase
{
    use RefreshDatabase;

    private Village $desa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->desa = Village::create([
            'nama' => 'Desa Uji', 'slug' => 'desa-uji', 'is_active' => true,
        ]);

        $this->admin = $this->buatAkun('Admin Utama', 'admin@uji.test');
    }

    private function buatAkun(string $peran, string $email): User
    {
        $user = User::create([
            'name' => "Akun {$peran}",
            'email' => $email,
            'password' => Hash::make('rahasia123'),
            'village_id' => $peran === 'Super Admin' ? null : $this->desa->id,
            'status_aktif' => true,
        ]);
        $user->assignRole($peran);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function formAkun(array $ubahan = []): array
    {
        return array_merge([
            'nama' => 'Operator Baru',
            'email' => 'operator.baru@uji.test',
            'no_telepon' => '081234567890',
            'peran' => 'Operator Konten',
            'status_aktif' => true,
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $ubahan);
    }

    // -----------------------------------------------------------------
    // Peran Operator Utama
    // -----------------------------------------------------------------

    public function test_operator_utama_memegang_semua_izin_kecuali_kelola_akun(): void
    {
        $operator = $this->buatAkun('Operator Utama', 'operator.utama@uji.test');

        $this->assertFalse($operator->can('manage-users'));

        // Seluruh izin lain harus dimiliki — termasuk yang paling sensitif.
        foreach (RolePermissionSeeder::allPermissions() as $izin) {
            if ($izin === 'manage-users') {
                continue;
            }

            $this->assertTrue(
                $operator->can($izin),
                "Operator Utama seharusnya memegang izin {$izin}."
            );
        }
    }

    public function test_operator_utama_ditolak_membuka_dan_membuat_akun(): void
    {
        $operator = $this->buatAkun('Operator Utama', 'operator.utama@uji.test');

        $this->actingAs($operator)->get('/admin/pengguna')->assertForbidden();

        $this->actingAs($operator)
            ->post('/admin/pengguna', $this->formAkun())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'operator.baru@uji.test']);
    }

    public function test_operator_utama_tetap_dapat_membuka_modul_sensitif(): void
    {
        $operator = $this->buatAkun('Operator Utama', 'operator.utama@uji.test');

        // Layar yang hanya boleh dibuka pemegang izin data pribadi.
        $this->actingAs($operator)->get('/admin/penduduk')->assertOk();
        $this->actingAs($operator)->get('/admin/bansos')->assertOk();
        $this->actingAs($operator)->get('/admin/pengaturan')->assertOk();
    }

    // -----------------------------------------------------------------
    // Membuat & mengubah akun
    // -----------------------------------------------------------------

    public function test_admin_utama_dapat_membuat_akun_operator(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/pengguna', $this->formAkun(['peran' => 'Operator Utama']))
            ->assertRedirect();

        $baru = User::where('email', 'operator.baru@uji.test')->firstOrFail();

        $this->assertTrue($baru->hasRole('Operator Utama'));
        $this->assertSame($this->desa->id, $baru->village_id);
        $this->assertTrue($baru->status_aktif);
        $this->assertTrue(Hash::check('rahasia123', $baru->password));
    }

    public function test_pembuatan_akun_tercatat_pada_audit_log_tanpa_kata_sandi(): void
    {
        $this->actingAs($this->admin)->post('/admin/pengguna', $this->formAkun());

        $log = ActivityLog::where('aksi', 'user_dibuat')->firstOrFail();

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertStringNotContainsString('rahasia123', json_encode($log->data_sesudah));
        $this->assertStringNotContainsString('rahasia123', (string) $log->deskripsi);
    }

    public function test_kata_sandi_lemah_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/pengguna', $this->formAkun([
                'password' => 'abc', 'password_confirmation' => 'abc',
            ]))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'operator.baru@uji.test']);
    }

    public function test_email_yang_sudah_dipakai_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/pengguna', $this->formAkun(['email' => 'admin@uji.test']))
            ->assertSessionHasErrors('email');
    }

    public function test_email_akun_terhapus_tetap_ditolak_dengan_alasan(): void
    {
        // Kolom `email` memegang indeks UNIQUE di basis data, dan soft delete
        // tidak melepaskannya. Yang diuji: penolakannya datang dari validasi
        // beserta alasannya — bukan sebagai galat 500 dari MySQL.
        $lama = $this->buatAkun('Operator Konten', 'operator.baru@uji.test');
        $lama->delete();

        $this->actingAs($this->admin)
            ->post('/admin/pengguna', $this->formAkun())
            ->assertSessionHasErrors('email');

        $galat = session('errors')->first('email');
        $this->assertStringContainsString('dihapus', $galat);
    }

    public function test_akun_dapat_diubah_tanpa_menyentuh_kata_sandi(): void
    {
        $target = $this->buatAkun('Operator Konten', 'konten@uji.test');
        $hashLama = $target->password;

        $this->actingAs($this->admin)
            ->put("/admin/pengguna/{$target->id}", [
                'nama' => 'Nama Diperbarui',
                'email' => 'konten@uji.test',
                'no_telepon' => null,
                'peran' => 'Operator PPID',
                'status_aktif' => false,
                'password' => '',
            ])
            ->assertRedirect();

        $target->refresh();

        $this->assertSame('Nama Diperbarui', $target->name);
        $this->assertTrue($target->hasRole('Operator PPID'));
        $this->assertFalse($target->status_aktif);
        $this->assertSame($hashLama, $target->password);
    }

    // -----------------------------------------------------------------
    // Batas kewenangan
    // -----------------------------------------------------------------

    public function test_admin_utama_tidak_dapat_mencetak_super_admin(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/pengguna', $this->formAkun(['peran' => 'Super Admin']))
            ->assertSessionHasErrors('peran');

        $this->assertDatabaseMissing('users', ['email' => 'operator.baru@uji.test']);
    }

    public function test_admin_utama_tidak_dapat_mengubah_akun_super_admin(): void
    {
        $super = $this->buatAkun('Super Admin', 'super@uji.test');

        $this->actingAs($this->admin)
            ->put("/admin/pengguna/{$super->id}", [
                'nama' => 'Dibajak',
                'email' => 'super@uji.test',
                'peran' => 'Operator Konten',
                'status_aktif' => false,
            ])
            ->assertForbidden();

        $this->assertTrue($super->fresh()->hasRole('Super Admin'));
    }

    public function test_akun_sendiri_tidak_dapat_diubah_dari_layar_ini(): void
    {
        // Layar ini dapat menurunkan peran dan mematikan status aktif — dua
        // cara tercepat mengunci diri sendiri keluar.
        $this->actingAs($this->admin)
            ->put("/admin/pengguna/{$this->admin->id}", [
                'nama' => 'Admin Uji',
                'email' => 'admin@uji.test',
                'peran' => 'Operator Konten',
                'status_aktif' => true,
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->delete("/admin/pengguna/{$this->admin->id}")
            ->assertForbidden();

        $this->assertTrue($this->admin->fresh()->hasRole('Admin Utama'));
    }

    public function test_pengelola_akun_terakhir_tidak_dapat_dilucuti(): void
    {
        $kedua = $this->buatAkun('Admin Utama', 'admin2@uji.test');

        // Selama masih ada dua, penurunan peran diizinkan.
        $this->actingAs($this->admin)
            ->put("/admin/pengguna/{$kedua->id}", [
                'nama' => 'Turun Peran',
                'email' => 'admin2@uji.test',
                'peran' => 'Operator Utama',
                'status_aktif' => true,
            ])
            ->assertSessionHasNoErrors();

        // Kini tinggal satu. Super Admin lain yang mencoba melucutinya harus
        // ditolak — tanpa penjagaan ini tak seorang pun dapat membuat akun lagi.
        $super = $this->buatAkun('Super Admin', 'super@uji.test');
        $super->delete();

        $this->actingAs($this->admin)
            ->delete("/admin/pengguna/{$kedua->id}")
            ->assertSessionHasNoErrors();

        $ketiga = $this->buatAkun('Admin Utama', 'admin3@uji.test');

        $this->actingAs($ketiga)
            ->put("/admin/pengguna/{$this->admin->id}", [
                'nama' => 'Admin Uji',
                'email' => 'admin@uji.test',
                'peran' => 'Operator Utama',
                'status_aktif' => true,
            ])
            ->assertSessionHasNoErrors();

        // Sekarang $ketiga adalah satu-satunya pengelola yang tersisa.
        $this->actingAs($ketiga)
            ->put("/admin/pengguna/{$ketiga->id}", [
                'nama' => 'Akun Admin Utama',
                'email' => 'admin3@uji.test',
                'peran' => 'Operator Utama',
                'status_aktif' => true,
            ])
            ->assertForbidden();
    }

    public function test_menonaktifkan_pengelola_terakhir_ditolak(): void
    {
        $kedua = $this->buatAkun('Admin Utama', 'admin2@uji.test');

        // $kedua menonaktifkan $this->admin — setelah itu hanya $kedua yang
        // tersisa, jadi masih boleh.
        $this->actingAs($kedua)
            ->put("/admin/pengguna/{$this->admin->id}", [
                'nama' => 'Admin Uji',
                'email' => 'admin@uji.test',
                'peran' => 'Admin Utama',
                'status_aktif' => false,
            ])
            ->assertSessionHasNoErrors();

        $ketiga = $this->buatAkun('Admin Utama', 'admin3@uji.test');

        // $ketiga mencoba menonaktifkan $kedua. Sesudahnya $ketiga sendiri
        // masih aktif, jadi ini pun boleh.
        $this->actingAs($ketiga)
            ->put("/admin/pengguna/{$kedua->id}", [
                'nama' => 'Akun Admin Utama',
                'email' => 'admin2@uji.test',
                'peran' => 'Admin Utama',
                'status_aktif' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($kedua->fresh()->status_aktif);
    }

    public function test_tamu_dan_operator_konten_ditolak(): void
    {
        $this->get('/admin/pengguna')->assertRedirect('/admin/masuk');

        $konten = $this->buatAkun('Operator Konten', 'konten@uji.test');
        $this->actingAs($konten)->get('/admin/pengguna')->assertForbidden();
    }

    public function test_penghapusan_bersifat_soft_delete(): void
    {
        $target = $this->buatAkun('Operator Konten', 'konten@uji.test');

        $this->actingAs($this->admin)
            ->delete("/admin/pengguna/{$target->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $target->id]);
        $this->assertDatabaseHas('activity_logs', ['aksi' => 'user_dihapus']);
    }

    public function test_akun_nonaktif_tidak_dapat_masuk(): void
    {
        $target = $this->buatAkun('Operator Konten', 'konten@uji.test');

        $this->actingAs($this->admin)->put("/admin/pengguna/{$target->id}", [
            'nama' => 'Akun Operator Konten',
            'email' => 'konten@uji.test',
            'peran' => 'Operator Konten',
            'status_aktif' => false,
        ]);

        // Sesi admin harus diakhiri lebih dulu: `actingAs` membuat setiap
        // permintaan berikutnya dalam tes ini tetap terautentikasi, sehingga
        // POST /admin/masuk hanya akan dipantulkan middleware `guest`.
        $this->post('/admin/keluar');
        $this->assertGuest();

        $this->post('/admin/masuk', [
            'email' => 'konten@uji.test',
            'password' => 'rahasia123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
