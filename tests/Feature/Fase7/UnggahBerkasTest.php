<?php

namespace Tests\Feature\Fase7;

use App\Models\Gallery;
use App\Models\News;
use App\Models\Official;
use App\Models\Product;
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

    public function test_unggah_banyak_foto_ke_album_dan_sampul_otomatis(): void
    {
        $this->masukSebagai('Operator Konten');

        $album = $this->postJson('/api/v1/admin/galeri', [
            'nama_album' => 'HUT Desa 2026',
            'tanggal_kegiatan' => '2026-08-17',
        ])->assertCreated();

        $id = $album->json('data.id');
        $this->assertNull($album->json('data.cover_image'));

        $this->postJson("/api/v1/admin/galeri/{$id}/foto", [
            'foto' => [$this->gambar('a.jpg'), $this->gambar('b.jpg'), $this->gambar('c.jpg')],
            'caption' => ['Upacara', 'Lomba', 'Panggung'],
        ])->assertCreated();

        $galeri = Gallery::with('photos')->findOrFail($id);

        $this->assertCount(3, $galeri->photos);
        $this->assertSame('Upacara', $galeri->photos[0]->caption);
        // Album tanpa sampul memakai foto pertama yang diunggah.
        $this->assertSame($galeri->photos[0]->path, $galeri->cover_image);

        foreach ($galeri->photos as $foto) {
            Storage::disk('public')->assertExists($foto->path);
        }
    }

    public function test_urutan_foto_melanjutkan_batch_sebelumnya(): void
    {
        $this->masukSebagai('Operator Konten');

        $id = $this->postJson('/api/v1/admin/galeri', ['nama_album' => 'Posyandu'])
            ->assertCreated()->json('data.id');

        $this->postJson("/api/v1/admin/galeri/{$id}/foto", ['foto' => [$this->gambar()]])
            ->assertCreated();
        $this->postJson("/api/v1/admin/galeri/{$id}/foto", ['foto' => [$this->gambar()]])
            ->assertCreated();

        $urutan = Gallery::findOrFail($id)->photos->pluck('urutan_tampil')->all();

        // Batch kedua tidak boleh menimpa posisi batch pertama.
        $this->assertSame([1, 2], $urutan);
    }

    public function test_menghapus_album_membuang_seluruh_berkas_fotonya(): void
    {
        $this->masukSebagai('Operator Konten');

        $id = $this->postJson('/api/v1/admin/galeri', [
            'nama_album' => 'Musyawarah Desa',
            'cover_image' => $this->gambar('sampul.jpg'),
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/admin/galeri/{$id}/foto", [
            'foto' => [$this->gambar('a.jpg'), $this->gambar('b.jpg')],
        ])->assertCreated();

        $galeri = Gallery::with('photos')->findOrFail($id);
        $berkas = $galeri->photos->pluck('path')->push($galeri->cover_image)->all();

        $this->deleteJson("/api/v1/admin/galeri/{$id}")->assertOk();

        foreach ($berkas as $path) {
            Storage::disk('public')->assertMissing($path);
        }
        $this->assertDatabaseMissing('gallery_photos', ['gallery_id' => $id]);
    }

    public function test_album_desa_lain_tidak_dapat_diakses(): void
    {
        $lain = Village::create([
            'nama' => 'Desa Lain', 'slug' => 'desa-lain', 'is_active' => true,
        ]);
        $album = Gallery::create([
            'village_id' => $lain->id, 'nama_album' => 'Rahasia', 'slug' => 'rahasia',
        ]);

        $this->masukSebagai('Operator Konten');

        $this->getJson("/api/v1/admin/galeri/{$album->id}")->assertNotFound();
        $this->postJson("/api/v1/admin/galeri/{$album->id}/foto", [
            'foto' => [$this->gambar()],
        ])->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Produk UMKM
    // ------------------------------------------------------------------

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

        $id = $this->postJson('/api/v1/admin/galeri', ['nama_album' => 'Uji'])
            ->assertCreated()->json('data.id');

        $this->postJson("/api/v1/admin/galeri/{$id}/foto", [
            'foto' => [UploadedFile::fake()->create('jahat.php', 10, 'application/x-php')],
        ])->assertStatus(422)->assertJsonValidationErrors('foto.0');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
