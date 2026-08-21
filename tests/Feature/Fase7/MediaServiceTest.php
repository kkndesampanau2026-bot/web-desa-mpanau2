<?php

namespace Tests\Feature\Fase7;

use App\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Layanan penyimpanan berkas publik CMS.
 *
 * Berkas di sini memang dimaksudkan publik, tetapi jalur unggahnya tetap
 * menjadi permukaan serangan — karena itu perlindungannya diuji sama
 * ketatnya dengan lampiran pengaduan.
 */
class MediaServiceTest extends TestCase
{
    private MediaService $media;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(MediaService::DISK);
        $this->media = app(MediaService::class);
    }

    public function test_gambar_tersimpan_pada_folder_yang_diminta(): void
    {
        $path = $this->media->simpanGambar(
            UploadedFile::fake()->image('foto.jpg', 800, 600),
            'aparat'
        );

        Storage::disk(MediaService::DISK)->assertExists($path);
        $this->assertStringStartsWith('aparat/', $path);
    }

    /**
     * Nama dari pengunggah tidak boleh dipakai sebagai nama berkas di disk —
     * ia dapat memuat karakter jebakan atau ekstensi ganda.
     */
    public function test_nama_berkas_diacak_bukan_nama_dari_pengunggah(): void
    {
        $path = $this->media->simpanGambar(
            UploadedFile::fake()->image('../../jahat.jpg', 400, 300),
            'uji'
        );

        $this->assertStringNotContainsString('jahat', $path);
        $this->assertStringNotContainsString('..', $path);
        $this->assertMatchesRegularExpression('/[0-9a-f-]{36}\.\w+$/', $path);
    }

    /** Gambar besar dikecilkan agar tidak menghabiskan kuota warga. */
    public function test_gambar_besar_dikecilkan_dan_dikonversi_ke_webp(): void
    {
        $path = $this->media->simpanGambar(
            UploadedFile::fake()->image('besar.jpg', 3000, 2000),
            'berita'
        );

        $this->assertStringEndsWith('.webp', $path);

        $ukuran = getimagesize(Storage::disk(MediaService::DISK)->path($path));
        $this->assertLessThanOrEqual(1600, $ukuran[0], 'Lebar gambar seharusnya dibatasi 1600px.');
    }

    /** Gambar yang sudah kecil tidak perlu dibesarkan. */
    public function test_gambar_kecil_tidak_dibesarkan(): void
    {
        $path = $this->media->simpanGambar(
            UploadedFile::fake()->image('kecil.jpg', 400, 300),
            'uji'
        );

        $ukuran = getimagesize(Storage::disk(MediaService::DISK)->path($path));
        $this->assertSame(400, $ukuran[0]);
    }

    /**
     * Tanpa penghapusan berkas lama, mengganti logo sepuluh kali akan
     * meninggalkan sembilan berkas yatim yang tak terlacak dari basis data.
     */
    public function test_berkas_lama_dihapus_saat_diganti(): void
    {
        $lama = $this->media->simpanGambar(
            UploadedFile::fake()->image('lama.jpg', 500, 400),
            'logo'
        );

        Storage::disk(MediaService::DISK)->assertExists($lama);

        $baru = $this->media->simpanGambar(
            UploadedFile::fake()->image('baru.jpg', 500, 400),
            'logo',
            pathLama: $lama
        );

        Storage::disk(MediaService::DISK)->assertMissing($lama);
        Storage::disk(MediaService::DISK)->assertExists($baru);
    }

    public function test_dokumen_pdf_tersimpan_tanpa_diproses(): void
    {
        $path = $this->media->simpanDokumen(
            UploadedFile::fake()->create('perdes.pdf', 200, 'application/pdf'),
            'ppid'
        );

        $this->assertStringEndsWith('.pdf', $path);
        Storage::disk(MediaService::DISK)->assertExists($path);
    }

    /** Berkas berbahaya ditolak berdasarkan MIME hasil deteksi isi. */
    public function test_berkas_selain_gambar_ditolak_sebagai_gambar(): void
    {
        $this->expectException(HttpException::class);

        $this->media->simpanGambar(
            UploadedFile::fake()->createWithContent('skrip.php', '<?php echo "jahat";'),
            'uji'
        );
    }

    public function test_gambar_ditolak_sebagai_dokumen(): void
    {
        $this->expectException(HttpException::class);

        $this->media->simpanDokumen(
            UploadedFile::fake()->image('bukan-pdf.jpg', 300, 200),
            'uji'
        );
    }

    public function test_hapus_aman_dipanggil_dengan_null(): void
    {
        $this->media->hapus(null);
        $this->media->hapus('');

        // Tidak boleh melempar galat; tercapai berarti lolos.
        $this->assertTrue(true);
    }
}
