<?php

namespace Tests\Feature\Publik;

use App\Models\BpdMember;
use App\Models\Official;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Halaman Pemerintah Desa — PRD 6.2 & 3.2.
 *
 * Dua hal yang dijaga di sini:
 *
 * 1. **Urutannya disimpulkan dari jabatan**, bukan dari angka yang diisi
 *    operator. Kolom `urutan_tampil` & `tingkat` sudah dihapus dari formulir
 *    CMS (docs/DEVIASI.md §C22), jadi kebenaran urutan sepenuhnya bergantung
 *    pada `Official::urut` dan `BpdMember::urut`.
 * 2. **BPD ikut tampil.** Kartu di Beranda menjanjikan "aparat desa dan Badan
 *    Permusyawaratan Desa"; sempat hanya aparat yang benar-benar ada.
 */
class PemerintahDesaTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();

        $this->village = Village::create([
            'nama' => 'Desa Uji', 'slug' => 'desa-uji', 'is_active' => true,
        ]);
    }

    private function aparat(string $nama, string $jabatan, bool $aktif = true): Official
    {
        return Official::create([
            'village_id' => $this->village->id,
            'nama' => $nama,
            'jabatan' => $jabatan,
            'status_aktif' => $aktif,
        ]);
    }

    private function anggotaBpd(string $nama, string $jabatan, bool $aktif = true): BpdMember
    {
        return BpdMember::create([
            'village_id' => $this->village->id,
            'nama' => $nama,
            'jabatan' => $jabatan,
            'status_aktif' => $aktif,
        ]);
    }

    public function test_aparat_diurutkan_menurut_jenjang_jabatan_bukan_urutan_input(): void
    {
        // Sengaja dimasukkan terbalik: yang paling bawah lebih dulu.
        $this->aparat('Dewi', 'Kepala Dusun 1');
        $this->aparat('Cakra', 'Kasi Pemerintahan');
        $this->aparat('Bayu', 'Kaur Keuangan');
        $this->aparat('Asep', 'Sekretaris Desa');
        $this->aparat('Eka', 'Kepala Desa');

        $this->get('/pemerintah')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/Pemerintah')
                ->where('aparat.0.nama', 'Eka')     // Kepala Desa
                ->where('aparat.1.nama', 'Asep')    // Sekretaris Desa
                ->where('aparat.2.nama', 'Bayu')    // Kaur
                ->where('aparat.3.nama', 'Cakra')   // Kasi
                ->where('aparat.4.nama', 'Dewi')    // Kepala Dusun
            );
    }

    /** Jabatan setara diurutkan abjad — urutan yang dapat dijelaskan. */
    public function test_jabatan_setara_diurutkan_menurut_abjad(): void
    {
        $this->aparat('Zulkifli', 'Kaur Umum');
        $this->aparat('Amir', 'Kaur Perencanaan');

        $this->get('/pemerintah')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Kaur Perencanaan mendahului Kaur Umum (abjad jabatan), dan
                // keduanya tetap berada di jenjang yang sama.
                ->where('aparat.0.nama', 'Amir')
                ->where('aparat.1.nama', 'Zulkifli')
            );
    }

    /** Jabatan di luar daftar baku tetap tampil, di urutan paling akhir. */
    public function test_jabatan_yang_tidak_dikenali_tetap_tampil_di_akhir(): void
    {
        $this->aparat('Bagus', 'Operator SID');
        $this->aparat('Andi', 'Kepala Desa');

        $this->get('/pemerintah')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('aparat', 2)
                ->where('aparat.0.nama', 'Andi')
                ->where('aparat.1.nama', 'Bagus')
            );
    }

    public function test_bpd_ikut_tampil_dan_diurutkan_menurut_hierarki_jabatan(): void
    {
        $this->anggotaBpd('Zahra', 'Anggota');
        $this->anggotaBpd('Ahmad', 'Anggota');
        $this->anggotaBpd('Sari', 'Sekretaris');
        $this->anggotaBpd('Wati', 'Wakil Ketua');
        $this->anggotaBpd('Karim', 'Ketua');

        $this->get('/pemerintah')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('bpd', 5)
                ->where('bpd.0.nama', 'Karim')
                ->where('bpd.1.nama', 'Wati')
                ->where('bpd.2.nama', 'Sari')
                // Sesama Anggota: abjad.
                ->where('bpd.3.nama', 'Ahmad')
                ->where('bpd.4.nama', 'Zahra')
            );
    }

    public function test_yang_nonaktif_tidak_tampil_di_halaman_publik(): void
    {
        $this->aparat('Masih Menjabat', 'Kepala Desa');
        $this->aparat('Sudah Purna', 'Sekretaris Desa', aktif: false);
        $this->anggotaBpd('Anggota Aktif', 'Ketua');
        $this->anggotaBpd('Anggota Lama', 'Anggota', aktif: false);

        $this->get('/pemerintah')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('aparat', 1)
                ->has('bpd', 1)
            )
            ->assertDontSee('Sudah Purna')
            ->assertDontSee('Anggota Lama');
    }

    /**
     * Halaman tetap dirender meski salah satu lembaga belum diisi — bagian
     * yang kosong menjelaskan dirinya sendiri, bukan menghilang (PRD 3.2).
     */
    public function test_halaman_tetap_dibuka_meski_hanya_satu_lembaga_terisi(): void
    {
        $this->aparat('Andi', 'Kepala Desa');

        $this->get('/pemerintah')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/Pemerintah')
                ->has('aparat', 1)
                ->has('bpd', 0)
            );
    }

    /**
     * Kolom yang sudah tidak dapat disunting tidak perlu ikut ke peramban.
     */
    public function test_payload_publik_tidak_lagi_memuat_urutan_tampil_maupun_tingkat(): void
    {
        $this->aparat('Andi', 'Kepala Desa');

        $this->get('/pemerintah')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('aparat.0', fn (Assert $baris) => $baris
                    ->where('nama', 'Andi')
                    ->missing('urutan_tampil')
                    ->missing('tingkat')
                    ->etc()
                )
            );
    }
}
