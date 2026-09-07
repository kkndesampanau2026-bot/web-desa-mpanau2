<?php

namespace Tests\Feature\Fase7;

use App\Models\News;
use App\Models\Official;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\User;
use App\Models\Village;
use App\Models\VillageProfile;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Unggah gambar & dokumen pada seluruh modul CMS.
 *
 * Sebelumnya 18 dari 19 kolom berkas di skema tidak punya jalur unggah sama
 * sekali — form CMS mengirim berkasnya, tetapi tidak pernah ada yang menyimpan.
 * Berkas uji ditulis ke disk palsu sehingga tidak menyentuh storage asli.
 */
class UnggahBerkasTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Storage::fake('public');

        $this->village = Village::create([
            'nama' => 'Desa Uji', 'slug' => 'desa-uji', 'is_active' => true,
        ]);
    }

    private function masukSebagai(string $role): User
    {
        $user = User::create([
            'village_id' => $this->village->id,
            'name' => 'Operator',
            'email' => uniqid().'@desa.test',
            'password' => Hash::make('rahasia123'),
            'status_aktif' => true,
        ]);
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    private function gambar(string $nama = 'foto.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nama, 800, 600);
    }

    // ------------------------------------------------------------------
    // Profil desa & aparat
    // ------------------------------------------------------------------

    public function test_foto_kepala_desa_tersimpan_ke_disk(): void
    {
        $this->masukSebagai('Admin Utama');

        $respons = $this->putJson('/api/v1/admin/profil', [
            'sejarah' => 'Riwayat singkat desa.',
            'foto_kepala_desa' => $this->gambar('kades.jpg'),
        ]);

        $respons->assertOk();

        $path = VillageProfile::where('village_id', $this->village->id)
            ->value('foto_kepala_desa');

        $this->assertNotNull($path, 'Path foto tidak tersimpan di basis data.');
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('profil/', $path);
    }

    public function test_foto_aparat_lama_dihapus_saat_diganti(): void
    {
        $this->masukSebagai('Admin Utama');

        $buat = $this->postJson('/api/v1/admin/officials', [
            'nama' => 'Budi Santoso',
            'jabatan' => 'Sekretaris Desa',
            'foto' => $this->gambar(),
        ])->assertCreated();

        $aparat = Official::findOrFail($buat->json('data.id'));
        $lama = $aparat->foto;
        Storage::disk('public')->assertExists($lama);

        $this->putJson("/api/v1/admin/officials/{$aparat->id}", [
            'nama' => 'Budi Santoso',
            'jabatan' => 'Sekretaris Desa',
            'foto' => $this->gambar('baru.jpg'),
        ])->assertOk();

        $baru = $aparat->fresh()->foto;

        $this->assertNotSame($lama, $baru);
        Storage::disk('public')->assertExists($baru);
        // Berkas lama harus ikut terbuang, bukan menumpuk sebagai sampah.
        Storage::disk('public')->assertMissing($lama);
    }

    public function test_menyimpan_tanpa_berkas_tidak_menghapus_foto_yang_ada(): void
    {
        $this->masukSebagai('Admin Utama');

        $buat = $this->postJson('/api/v1/admin/officials', [
            'nama' => 'Siti Aminah',
            'jabatan' => 'Kaur Keuangan',
            'foto' => $this->gambar(),
        ])->assertCreated();

        $aparat = Official::findOrFail($buat->json('data.id'));
        $foto = $aparat->foto;

        // Admin mengubah jabatan saja, tanpa menyentuh input berkas.
        $this->putJson("/api/v1/admin/officials/{$aparat->id}", [
            'nama' => 'Siti Aminah',
            'jabatan' => 'Kaur Perencanaan',
        ])->assertOk();

        $this->assertSame($foto, $aparat->fresh()->foto);
        Storage::disk('public')->assertExists($foto);
    }

    // ------------------------------------------------------------------
    // Berita & PPID
    // ------------------------------------------------------------------

    public function test_gambar_utama_berita_tersimpan(): void
    {
        $this->masukSebagai('Operator Konten');

        $respons = $this->postJson('/api/v1/admin/berita', [
            'judul' => 'Kerja Bakti Warga',
            'konten' => '<p>Isi berita.</p>',
            'status' => 'draft',
            'gambar_utama' => $this->gambar('sampul.png'),
        ])->assertCreated();

        $path = News::findOrFail($respons->json('data.id'))->gambar_utama;

        $this->assertStringStartsWith('berita/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_dokumen_ppid_menerima_pdf_dan_menolak_gambar(): void
    {
        $this->masukSebagai('Operator PPID');

        $respons = $this->postJson('/api/v1/admin/ppid/dasar-hukum', [
            'judul_regulasi' => 'Perdes Keterbukaan Informasi',
            'tahun' => 2026,
            'file_pdf' => UploadedFile::fake()->create('perdes.pdf', 120, 'application/pdf'),
        ])->assertCreated();

        $path = $respons->json('data.file_pdf');
        $this->assertStringEndsWith('.pdf', $path);
        Storage::disk('public')->assertExists($path);

        // Kolom dokumen tidak boleh menerima gambar: tautan unduhan PPID
        // dianggap berkas resmi yang dapat diarsipkan.
        $this->postJson('/api/v1/admin/ppid/dasar-hukum', [
            'judul_regulasi' => 'Perdes Lain',
            'file_pdf' => $this->gambar(),
        ])->assertStatus(422)->assertJsonValidationErrors('file_pdf');
    }

    // ------------------------------------------------------------------
    // Galeri
    // ------------------------------------------------------------------

    public function test_unggah_banyak_foto_produk_tersimpan(): void
    {
        $this->masukSebagai('Operator Konten');

        $id = $this->postJson('/api/v1/admin/produk', [
            'nama_produk' => 'Keripik Pisang',
            'nama_penjual' => 'Ibu Ani',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/admin/produk/{$id}/foto", [
            'foto' => [$this->gambar('a.jpg'), $this->gambar('b.jpg'), $this->gambar('c.jpg')],
        ])->assertCreated();

        $produk = Product::with('photos')->findOrFail($id);

        $this->assertCount(3, $produk->photos);

        foreach ($produk->photos as $foto) {
            Storage::disk('public')->assertExists($foto->path);
        }
    }

    public function test_urutan_foto_melanjutkan_batch_sebelumnya(): void
    {
        $this->masukSebagai('Operator Konten');

        $id = $this->postJson('/api/v1/admin/produk', [
            'nama_produk' => 'Kopi Bubuk',
            'nama_penjual' => 'Pak Budi',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/admin/produk/{$id}/foto", ['foto' => [$this->gambar()]])
            ->assertCreated();
        $this->postJson("/api/v1/admin/produk/{$id}/foto", ['foto' => [$this->gambar()]])
            ->assertCreated();

        $urutan = Product::findOrFail($id)->photos->pluck('urutan_tampil')->all();

        // Batch kedua tidak boleh menimpa posisi batch pertama.
        $this->assertSame([1, 2], $urutan);
    }



    // ------------------------------------------------------------------
    // Produk UMKM
    // ------------------------------------------------------------------

    public function test_foto_yang_salah_unggah_dapat_dihapus_beserta_berkasnya(): void
    {
        $this->masukSebagai('Operator Konten');

        $id = $this->postJson('/api/v1/admin/produk', [
            'nama_produk' => 'Keripik Pisang',
            'nama_penjual' => 'Ibu Ani',
        ])->assertCreated()->json('data.id');

        $foto = $this->postJson("/api/v1/admin/produk/{$id}/foto", [
            'foto' => [$this->gambar('benar.jpg'), $this->gambar('salah.jpg')],
        ])->assertCreated()->json('data');

        $path = ProductPhoto::findOrFail($foto[1]['id'])->path;
        Storage::disk('public')->assertExists($path);

        $this->deleteJson("/api/v1/admin/produk/{$id}/foto/{$foto[1]['id']}")->assertOk();

        // Barisnya hilang DAN berkasnya ikut terbuang — foto yang salah unggah
        // tidak boleh menyisakan berkas yatim di penyimpanan server.
        $this->assertDatabaseMissing('product_photos', ['id' => $foto[1]['id']]);
        Storage::disk('public')->assertMissing($path);

        // Foto yang benar tidak ikut terbawa.
        $this->assertDatabaseHas('product_photos', ['id' => $foto[0]['id']]);
    }

    public function test_keterangan_foto_dapat_diperbaiki_tanpa_unggah_ulang(): void
    {
        $this->masukSebagai('Operator Konten');

        $wisata = $this->postJson('/api/v1/admin/wisata', ['nama' => 'Air Terjun'])
            ->assertCreated()->json('data.id');

        $foto = $this->postJson("/api/v1/admin/wisata/{$wisata}/foto", [
            'foto' => [$this->gambar()],
            'caption' => ['Keterangn slah ketik'],
        ])->assertCreated()->json('data.0.id');

        $this->putJson("/api/v1/admin/wisata/{$wisata}/foto/{$foto}", [
            'caption' => 'Air Terjun Mpanau dari sisi timur',
        ])->assertOk();

        $this->assertDatabaseHas('tourism_spot_photos', [
            'id' => $foto,
            'caption' => 'Air Terjun Mpanau dari sisi timur',
            // Tanpa alt_text tersendiri, keterangannya dipakai sekaligus
            // sebagai teks alternatif — bukan dibiarkan kosong.
            'alt_text' => 'Air Terjun Mpanau dari sisi timur',
        ]);
    }

    public function test_urutan_foto_dapat_digeser_untuk_mengganti_gambar_utama(): void
    {
        $this->masukSebagai('Operator Konten');

        $id = $this->postJson('/api/v1/admin/produk', [
            'nama_produk' => 'Kopi Bubuk',
            'nama_penjual' => 'Pak Budi',
        ])->assertCreated()->json('data.id');

        $foto = $this->postJson("/api/v1/admin/produk/{$id}/foto", [
            'foto' => [$this->gambar('a.jpg'), $this->gambar('b.jpg')],
        ])->assertCreated()->json('data');

        [$pertama, $kedua] = [$foto[0]['id'], $foto[1]['id']];

        // Foto kedua dinaikkan menjadi gambar utama.
        $this->putJson("/api/v1/admin/produk/{$id}/foto/{$kedua}/geser", ['arah' => 'naik'])
            ->assertOk();

        $urut = ProductPhoto::whereIn('id', [$pertama, $kedua])
            ->orderBy('urutan_tampil')->pluck('id')->all();

        $this->assertSame([$kedua, $pertama], $urut);

        // Sudah di ujung: bukan galat, hanya tidak ada yang berubah.
        $this->putJson("/api/v1/admin/produk/{$id}/foto/{$kedua}/geser", ['arah' => 'naik'])
            ->assertOk();

        $this->assertSame(
            [$kedua, $pertama],
            ProductPhoto::whereIn('id', [$pertama, $kedua])
                ->orderBy('urutan_tampil')->pluck('id')->all()
        );
    }

    public function test_foto_produk_lain_tidak_dapat_disunting_atau_digeser(): void
    {
        $this->masukSebagai('Operator Konten');

        $buat = fn (string $nama) => $this->postJson('/api/v1/admin/produk', [
            'nama_produk' => $nama,
            'nama_penjual' => 'Ibu Ani',
        ])->assertCreated()->json('data.id');

        $a = $buat('Keripik');
        $b = $buat('Kopi');

        $foto = $this->postJson("/api/v1/admin/produk/{$a}/foto", ['foto' => [$this->gambar()]])
            ->assertCreated()->json('data.0.id');

        // Menebak id foto lewat produk lain harus gagal pada SEMUA aksinya,
        // bukan hanya pada penghapusan.
        $this->putJson("/api/v1/admin/produk/{$b}/foto/{$foto}", ['caption' => 'Dibajak'])
            ->assertNotFound();
        $this->putJson("/api/v1/admin/produk/{$b}/foto/{$foto}/geser", ['arah' => 'naik'])
            ->assertNotFound();
    }

    public function test_menghapus_produk_ikut_membuang_berkas_fotonya(): void
    {
        $this->masukSebagai('Operator Konten');

        $id = $this->postJson('/api/v1/admin/produk', [
            'nama_produk' => 'Gula Aren',
            'nama_penjual' => 'Pak Karta',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/admin/produk/{$id}/foto", [
            'foto' => [$this->gambar('a.jpg'), $this->gambar('b.jpg')],
        ])->assertCreated();

        $berkas = ProductPhoto::where('product_id', $id)->pluck('path')->all();
        $this->assertCount(2, $berkas);

        $this->deleteJson("/api/v1/admin/produk/{$id}")->assertOk();

        // Barisnya lenyap lewat cascade; berkasnya harus ikut, kalau tidak ia
        // tertinggal selamanya tanpa pemilik yang dapat ditelusuri.
        foreach ($berkas as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_foto_produk_milik_produk_lain_tidak_dapat_dihapus(): void
    {
        $this->masukSebagai('Operator Konten');

        $buat = fn (string $nama) => $this->postJson('/api/v1/admin/produk', [
            'nama_produk' => $nama,
            'nama_penjual' => 'Ibu Ani',
        ])->assertCreated()->json('data.id');

        $a = $buat('Keripik Pisang');
        $b = $buat('Kopi Bubuk');

        $foto = $this->postJson("/api/v1/admin/produk/{$a}/foto", ['foto' => [$this->gambar()]])
            ->assertCreated()->json('data.0.id');

        // Menebak id foto lewat produk lain harus gagal, bukan menghapus.
        $this->deleteJson("/api/v1/admin/produk/{$b}/foto/{$foto}")->assertNotFound();

        $this->assertDatabaseHas('product_photos', ['id' => $foto, 'product_id' => $a]);
        Storage::disk('public')->assertExists(
            Product::findOrFail($a)->photos()->value('path')
        );
    }

    // ------------------------------------------------------------------
    // Penyiasatan method (`_method`) — jalur yang dipakai Admin App
    // ------------------------------------------------------------------

    /**
     * Admin App mengirim pembaruan berberkas sebagai POST + `_method=PUT`,
     * karena PHP tidak mengurai body multipart pada request PUT. Uji ini
     * mengunci perilaku itu: bila rute PUT diubah menjadi hanya-PUT, formulir
     * CMS akan gagal secara diam-diam dan hanya ketahuan di produksi.
     */
    public function test_pembaruan_multipart_lewat_method_spoofing(): void
    {
        $this->masukSebagai('Admin Utama');

        $respons = $this->post('/api/v1/admin/profil', [
            '_method' => 'PUT',
            'sejarah' => 'Riwayat desa.',
            'foto_kepala_desa' => $this->gambar('kades.jpg'),
        ]);

        $respons->assertOk();

        $path = VillageProfile::where('village_id', $this->village->id)
            ->value('foto_kepala_desa');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    /**
     * `keFormData` mengirim daftar kosong sebagai string kosong, bukan
     * melewatinya — supaya operator tetap bisa MENGOSONGKAN daftar yang
     * sebelumnya terisi. Uji ini memastikan backend menerima bentuk itu.
     */
    public function test_daftar_dapat_dikosongkan_lewat_string_kosong(): void
    {
        $this->masukSebagai('Admin Utama');

        $this->putJson('/api/v1/admin/profil', [
            'misi' => ['Meningkatkan pelayanan', 'Membangun infrastruktur'],
        ])->assertOk();

        $this->assertCount(
            2,
            VillageProfile::where('village_id', $this->village->id)->value('misi')
        );

        $this->post('/api/v1/admin/profil', [
            '_method' => 'PUT',
            'misi' => '',
        ])->assertOk();

        $this->assertNull(VillageProfile::where('village_id', $this->village->id)->value('misi'));
    }

    public function test_berkas_berbahaya_ditolak(): void
    {
        $this->masukSebagai('Operator Konten');

        $id = $this->postJson('/api/v1/admin/produk', [
            'nama_produk' => 'Uji',
            'nama_penjual' => 'Uji',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/admin/produk/{$id}/foto", [
            'foto' => [UploadedFile::fake()->create('jahat.php', 10, 'application/x-php')],
        ])->assertStatus(422)->assertJsonValidationErrors('foto.0');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
