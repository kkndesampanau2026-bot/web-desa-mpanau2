<?php

namespace Tests\Feature\Publik;

use App\Models\Setting;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Banner beranda yang dapat diganti perangkat desa (Pengaturan Umum).
 *
 * Sebelumnya banner berupa berkas statis di `public/gambar/`; menggantinya
 * menuntut akses ke server. Yang diuji di sini bukan sekadar "kolomnya ada",
 * melainkan rantai lengkapnya: unggahan lewat CMS tersimpan, muncul sebagai
 * prop bersama Inertia, dan — yang paling mudah terlewat — Beranda tetap
 * tampil normal ketika banner belum pernah diunggah, karena itulah keadaan
 * setiap desa pada hari pertama.
 */
class BannerBerandaTest extends TestCase
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

    private function operator(): User
    {
        $user = User::create([
            'name' => 'Admin Uji',
            'email' => 'admin@uji.test',
            'password' => Hash::make('rahasia'),
            'village_id' => $this->village->id,
        ]);
        $user->assignRole('Admin Utama');

        return $user;
    }

    public function test_banner_kosong_membuat_beranda_memakai_berkas_bawaan(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pengaturan.banner', null));
    }

    public function test_operator_dapat_mengunggah_banner_dan_tampil_di_beranda(): void
    {
        Storage::fake('public');

        $this->actingAs($this->operator())
            ->put('/api/v1/admin/settings', [
                'nama_desa' => 'Desa Uji',
                'banner' => UploadedFile::fake()->image('banner.jpg', 1600, 900),
            ])
            ->assertOk();

        $banner = Setting::where('village_id', $this->village->id)->value('banner');
        $this->assertNotNull($banner, 'Path banner seharusnya tersimpan pada settings.');
        Storage::disk('public')->assertExists($banner);

        // Nama berkas tidak boleh berasal dari pengunggah — lihat MediaService.
        $this->assertStringNotContainsString('banner.jpg', $banner);

        $this->get('/')->assertInertia(
            fn (Assert $page) => $page->where('pengaturan.banner', asset('storage/'.$banner))
        );
    }

    public function test_berkas_selain_gambar_ditolak(): void
    {
        Storage::fake('public');

        $this->actingAs($this->operator())
            ->putJson('/api/v1/admin/settings', [
                'nama_desa' => 'Desa Uji',
                'banner' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['banner']);
    }

    public function test_menyimpan_tanpa_banner_tidak_menghapus_banner_lama(): void
    {
        Storage::fake('public');
        $operator = $this->operator();

        $this->actingAs($operator)->put('/api/v1/admin/settings', [
            'nama_desa' => 'Desa Uji',
            'banner' => UploadedFile::fake()->image('banner.jpg', 1600, 900),
        ])->assertOk();

        $sebelum = Setting::where('village_id', $this->village->id)->value('banner');

        // Operator menyunting nomor telepon saja; kolom berkas dibiarkan kosong.
        $this->actingAs($operator)->put('/api/v1/admin/settings', [
            'nama_desa' => 'Desa Uji',
            'telepon' => '0451-123456',
        ])->assertOk();

        $this->assertSame(
            $sebelum,
            Setting::where('village_id', $this->village->id)->value('banner'),
            'Menyimpan formulir tanpa memilih berkas tidak boleh mengosongkan banner.'
        );
    }
}
