<?php

namespace Tests\Feature\Pengaturan;

use App\Models\SocialMediaLink;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Baris WhatsApp pada daftar sosial media — PRD 6.17.
 *
 * Operator mengisi NOMOR; server yang menyusun tautan wa.me-nya. Berkas ini
 * menjaga dua hal sekaligus: bentuk yang tersimpan selalu sama dari mana pun
 * asalnya, dan baris non-WhatsApp tetap menuntut alamat yang sah.
 */
class SosialMediaTest extends TestCase
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

        $user = User::create([
            'village_id' => $this->village->id,
            'name' => 'Operator Pengaturan',
            'email' => 'pengaturan@desa.test',
            'password' => Hash::make('rahasia123'),
            'status_aktif' => true,
        ]);
        $user->assignRole('Admin Utama');

        Sanctum::actingAs($user);
    }

    /** @param  array<int, array<string, string>>  $sosialMedia */
    private function simpan(array $sosialMedia)
    {
        return $this->putJson('/api/v1/admin/settings', [
            'nama_desa' => 'Desa Uji',
            'sosial_media' => $sosialMedia,
        ]);
    }

    public function test_nomor_whatsapp_disimpan_sebagai_tautan_wa_me(): void
    {
        $this->simpan([
            ['platform' => 'WhatsApp', 'url' => '0812-3456-7890'],
        ])->assertOk();

        $this->assertSame(
            'https://wa.me/6281234567890',
            SocialMediaLink::where('platform', 'WhatsApp')->value('url')
        );
    }

    /**
     * Beragam gaya penulisan yang lazim dipakai operator harus bermuara pada
     * satu tautan yang sama — termasuk tautan wa.me yang ditempel apa adanya,
     * karena itulah yang tersimpan setelah penyimpanan pertama.
     */
    public function test_beragam_bentuk_isian_menghasilkan_tautan_yang_sama(): void
    {
        foreach ([
            '081234567890',
            '+62 812 3456 7890',
            '6281234567890',
            'https://wa.me/6281234567890',
        ] as $isian) {
            $this->simpan([['platform' => 'wa', 'url' => $isian]])->assertOk();

            $this->assertSame(
                'https://wa.me/6281234567890',
                SocialMediaLink::where('platform', 'wa')->value('url'),
                "Isian {$isian} seharusnya menghasilkan tautan yang sama."
            );
        }
    }

    public function test_nomor_whatsapp_yang_tidak_masuk_akal_ditolak(): void
    {
        $this->simpan([
            ['platform' => 'WhatsApp', 'url' => 'bukan-nomor'],
        ])->assertStatus(422)->assertJsonValidationErrors(['sosial_media.0.url']);

        $this->assertSame(0, SocialMediaLink::count());
    }

    /**
     * Alamat situs lain yang terlanjur diketik pada baris WhatsApp ditolak,
     * bukan diam-diam diperas menjadi angka yang kebetulan ada di dalamnya.
     */
    public function test_tautan_selain_whatsapp_ditolak_pada_baris_whatsapp(): void
    {
        $this->simpan([
            ['platform' => 'WhatsApp', 'url' => 'https://facebook.com/desa62812'],
        ])->assertStatus(422)->assertJsonValidationErrors(['sosial_media.0.url']);
    }

    public function test_platform_lain_tetap_menuntut_alamat_yang_sah(): void
    {
        $this->simpan([
            ['platform' => 'Facebook', 'url' => '0812-3456-7890'],
        ])->assertStatus(422)->assertJsonValidationErrors(['sosial_media.0.url']);

        $this->simpan([
            ['platform' => 'Facebook', 'url' => 'https://facebook.com/desampanau'],
        ])->assertOk();

        $this->assertSame(
            'https://facebook.com/desampanau',
            SocialMediaLink::where('platform', 'Facebook')->value('url')
        );
    }

    /**
     * Kolom WhatsApp pada "Identitas & Wilayah" sudah dihapus dari formulir
     * (docs/DEVIASI.md §C20); prop bersama tidak boleh lagi mengirimkannya,
     * supaya tidak ada dua sumber untuk satu nomor.
     */
    public function test_kontak_pada_prop_bersama_tidak_lagi_memuat_whatsapp(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pengaturan.kontak.telepon')
                ->has('pengaturan.kontak.email')
                ->missing('pengaturan.kontak.whatsapp')
            );
    }
}
