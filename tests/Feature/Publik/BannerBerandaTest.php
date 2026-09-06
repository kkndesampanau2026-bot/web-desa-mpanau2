<?php

namespace Tests\Feature\Publik;

use App\Http\Controllers\Admin\BannerController;
use App\Models\User;
use App\Models\Village;
use App\Models\VillageBanner;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Banner beranda yang dapat diganti perangkat desa (CMS → Banner Beranda).
 *
 * Semula satu kolom `settings.banner`; kini daftar gambar yang digilir hero.
 * Yang diuji bukan sekadar "kolomnya ada", melainkan rantai lengkapnya:
 * unggahan lewat CMS tersimpan, muncul berurutan sebagai prop bersama Inertia,
 * dan — yang paling mudah terlewat — Beranda tetap tampil normal ketika belum
 * ada satu pun banner, karena itulah keadaan setiap desa pada hari pertama.
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

    private function operator(string $peran = 'Admin Utama'): User
    {
        $user = User::create([
            'name' => 'Admin Uji',
            'email' => 'admin'.uniqid().'@uji.test',
            'password' => Hash::make('rahasia123'),
            'village_id' => $this->village->id,
        ]);
        $user->assignRole($peran);

        return $user;
    }

    // -----------------------------------------------------------------
    // Sisi publik
    // -----------------------------------------------------------------

    public function test_tanpa_banner_beranda_memakai_berkas_bawaan(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pengaturan.banner', []));
    }

    public function test_banner_tampil_berurutan_sebagai_prop_bersama(): void
    {
        // Sengaja disisipkan dengan urutan terbalik dari urutan pembuatan,
        // supaya yang diuji benar-benar `urutan_tampil` dan bukan kebetulan id.
        VillageBanner::create([
            'village_id' => $this->village->id, 'path' => 'banner/kedua.webp',
            'judul' => 'Sawah', 'urutan_tampil' => 2,
        ]);
        VillageBanner::create([
            'village_id' => $this->village->id, 'path' => 'banner/pertama.webp',
            'judul' => 'Balai Desa', 'urutan_tampil' => 1,
        ]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('pengaturan.banner', 2)
            ->where('pengaturan.banner.0.url', asset('storage/banner/pertama.webp'))
            ->where('pengaturan.banner.0.judul', 'Balai Desa')
            ->where('pengaturan.banner.1.url', asset('storage/banner/kedua.webp'))
        );
    }

    // -----------------------------------------------------------------
    // Sisi CMS
    // -----------------------------------------------------------------

    public function test_operator_dapat_mengunggah_beberapa_banner_sekaligus(): void
    {
        Storage::fake('public');

        $this->actingAs($this->operator())
            ->post('/admin/banner', [
                'gambar' => [
                    UploadedFile::fake()->image('satu.jpg', 1600, 900),
                    UploadedFile::fake()->image('dua.jpg', 1600, 900),
                ],
            ])
            ->assertRedirect();

        $banner = VillageBanner::where('village_id', $this->village->id)
            ->orderBy('urutan_tampil')->get();

        $this->assertCount(2, $banner);

        foreach ($banner as $b) {
            Storage::disk('public')->assertExists($b->path);
            // Nama berkas tidak boleh berasal dari pengunggah — lihat MediaService.
            $this->assertStringNotContainsString('satu.jpg', $b->path);
            $this->assertStringNotContainsString('dua.jpg', $b->path);
        }

        // Urutannya harus berbeda, kalau tidak tombol naik/turun tidak berarti.
        $this->assertNotSame($banner[0]->urutan_tampil, $banner[1]->urutan_tampil);
    }

    public function test_berkas_selain_gambar_ditolak(): void
    {
        Storage::fake('public');

        $this->actingAs($this->operator())
            ->post('/admin/banner', [
                'gambar' => [UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf')],
            ])
            ->assertSessionHasErrors('gambar.0');

        $this->assertSame(0, VillageBanner::count());
    }

    public function test_jumlah_banner_dibatasi(): void
    {
        Storage::fake('public');

        for ($i = 0; $i < BannerController::MAKS_BANNER; $i++) {
            VillageBanner::create([
                'village_id' => $this->village->id,
                'path' => "banner/ada-{$i}.webp",
                'urutan_tampil' => $i,
            ]);
        }

        $this->actingAs($this->operator())
            ->post('/admin/banner', ['gambar' => [UploadedFile::fake()->image('lebih.jpg')]])
            ->assertSessionHasErrors('gambar');

        // Pesannya harus menyebut kuota, bukan ukuran berkas — keduanya
        // memakai aturan `max` dan mudah tertukar.
        $this->assertStringContainsString('batas', session('errors')->first('gambar'));
        $this->assertSame(BannerController::MAKS_BANNER, VillageBanner::count());
    }

    public function test_urutan_dapat_digeser_dengan_menukar_tetangga(): void
    {
        $pertama = VillageBanner::create([
            'village_id' => $this->village->id, 'path' => 'banner/a.webp', 'urutan_tampil' => 1,
        ]);
        $kedua = VillageBanner::create([
            'village_id' => $this->village->id, 'path' => 'banner/b.webp', 'urutan_tampil' => 2,
        ]);

        $this->actingAs($this->operator())
            ->put("/admin/banner/{$kedua->id}/geser", ['arah' => 'naik'])
            ->assertRedirect();

        $this->assertSame(2, $pertama->fresh()->urutan_tampil);
        $this->assertSame(1, $kedua->fresh()->urutan_tampil);

        // Sudah di ujung: bukan galat, hanya tidak ada yang berubah.
        $this->actingAs($this->operator())
            ->put("/admin/banner/{$kedua->id}/geser", ['arah' => 'naik'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $kedua->fresh()->urutan_tampil);
    }

    public function test_urutan_warisan_yang_kembar_tetap_dapat_ditukar(): void
    {
        // Baris hasil migrasi dari kolom lama semuanya bernomor 0. Menukar dua
        // nilai yang sama tidak memindahkan apa pun bila tidak ditangani.
        $a = VillageBanner::create([
            'village_id' => $this->village->id, 'path' => 'banner/a.webp', 'urutan_tampil' => 0,
        ]);
        $b = VillageBanner::create([
            'village_id' => $this->village->id, 'path' => 'banner/b.webp', 'urutan_tampil' => 0,
        ]);

        $this->actingAs($this->operator())
            ->put("/admin/banner/{$b->id}/geser", ['arah' => 'naik'])
            ->assertRedirect();

        $this->assertLessThan(
            $a->fresh()->urutan_tampil,
            $b->fresh()->urutan_tampil,
            'Banner kedua seharusnya kini berada di atas yang pertama.'
        );
    }

    public function test_menghapus_banner_ikut_menghapus_berkasnya(): void
    {
        Storage::fake('public');

        $this->actingAs($this->operator())
            ->post('/admin/banner', ['gambar' => [UploadedFile::fake()->image('satu.jpg')]]);

        $banner = VillageBanner::firstOrFail();
        Storage::disk('public')->assertExists($banner->path);

        $this->actingAs($this->operator())
            ->delete("/admin/banner/{$banner->id}")
            ->assertRedirect();

        $this->assertSame(0, VillageBanner::count());
        Storage::disk('public')->assertMissing($banner->path);
    }

    public function test_keterangan_banner_dapat_disunting(): void
    {
        $banner = VillageBanner::create([
            'village_id' => $this->village->id, 'path' => 'banner/a.webp', 'urutan_tampil' => 1,
        ]);

        $this->actingAs($this->operator())
            ->put("/admin/banner/{$banner->id}", ['judul' => 'Panorama Sawah'])
            ->assertRedirect();

        $this->assertSame('Panorama Sawah', $banner->fresh()->judul);
    }

    public function test_perubahan_banner_langsung_terlihat_di_beranda(): void
    {
        Storage::fake('public');

        // Beranda dibuka lebih dulu agar prop bersamanya ter-cache.
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('pengaturan.banner', []));

        $this->actingAs($this->operator())
            ->post('/admin/banner', ['gambar' => [UploadedFile::fake()->image('satu.jpg')]]);

        // Tanpa pembersihan cache di BannerController, baris ini masih kosong
        // sampai satu jam berlalu — dan operator menyimpulkan unggahannya gagal.
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('pengaturan.banner', 1));
    }

    // -----------------------------------------------------------------
    // Kewenangan
    // -----------------------------------------------------------------

    public function test_tamu_dan_operator_tanpa_izin_ditolak(): void
    {
        $this->get('/admin/banner')->assertRedirect('/admin/masuk');

        $konten = $this->operator('Operator Konten');
        $this->actingAs($konten)->get('/admin/banner')->assertForbidden();
        $this->actingAs($konten)
            ->post('/admin/banner', ['gambar' => [UploadedFile::fake()->image('satu.jpg')]])
            ->assertForbidden();
    }

    public function test_banner_desa_lain_tidak_dapat_disentuh(): void
    {
        $desaLain = Village::create([
            'nama' => 'Desa Lain', 'slug' => 'desa-lain', 'is_active' => true,
        ]);
        $milikOrangLain = VillageBanner::create([
            'village_id' => $desaLain->id, 'path' => 'banner/lain.webp', 'urutan_tampil' => 1,
        ]);

        $this->actingAs($this->operator())
            ->delete("/admin/banner/{$milikOrangLain->id}")
            ->assertNotFound();

        $this->assertNotNull($milikOrangLain->fresh());
    }
}
