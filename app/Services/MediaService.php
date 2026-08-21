<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Image;
use Throwable;

/**
 * Penyimpanan berkas publik — gambar & dokumen CMS.
 *
 * BERBEDA dari LampiranPengaduanService, berkas di sini memang dimaksudkan
 * untuk dilihat publik: logo desa, foto aparat, gambar berita, dokumen PPID.
 * Karena itu ia memakai disk `public` yang tertaut ke web root.
 *
 * Empat hal yang tetap dijaga meski berkasnya publik:
 *
 * 1. **Nama berkas diacak.** Nama dari pengunggah tidak pernah dipakai; ia
 *    dapat memuat karakter jebakan (`../`, null byte) atau ekstensi ganda
 *    seperti `foto.jpg.php`.
 *
 * 2. **MIME diperiksa dari isi berkas**, bukan dari ekstensi maupun header
 *    Content-Type yang dikirim klien — keduanya dikendalikan pengunggah.
 *
 * 3. **Ekstensi ditentukan ulang server** dari MIME yang terdeteksi.
 *
 * 4. **Berkas lama dihapus saat diganti.** Tanpa ini, mengganti logo desa
 *    sepuluh kali akan meninggalkan sembilan berkas yatim yang tidak pernah
 *    terhapus dan tidak lagi dapat ditelusuri dari basis data.
 */
class MediaService
{
    public const DISK = 'public';

    /**
     * MIME gambar yang diterima beserta ekstensi yang DIPAKSAKAN server.
     *
     * @var array<string, string>
     */
    public const MIME_GAMBAR = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @var array<string, string> */
    public const MIME_DOKUMEN = [
        'application/pdf' => 'pdf',
    ];

    /** Ukuran maksimum unggahan (kilobyte). */
    public const MAKS_GAMBAR_KB = 5120;   // 5 MB

    public const MAKS_DOKUMEN_KB = 10240; // 10 MB

    /**
     * Lebar maksimum gambar setelah diproses.
     *
     * Foto dari ponsel admin desa lazim berukuran 3000–4000 px dan 4–8 MB.
     * Menyajikannya apa adanya akan menghabiskan kuota warga sekaligus
     * menghancurkan target muat <2 detik pada 4G (PRD 12.1).
     */
    private const LEBAR_MAKS = 1600;

    /**
     * Menyimpan gambar: dikecilkan, dikonversi ke WebP, lalu disimpan.
     *
     * @param  string|null  $pathLama  berkas yang digantikan, akan dihapus
     */
    public function simpanGambar(
        UploadedFile $berkas,
        string $folder,
        ?string $pathLama = null,
    ): string {
        $path = $this->simpanMentah($berkas, $folder, self::MIME_GAMBAR);
        $path = $this->prosesGambar($path);

        $this->hapus($pathLama);

        return $path;
    }

    /** Menyimpan dokumen (PDF) apa adanya — tidak ada pemrosesan isi. */
    public function simpanDokumen(
        UploadedFile $berkas,
        string $folder,
        ?string $pathLama = null,
    ): string {
        $path = $this->simpanMentah($berkas, $folder, self::MIME_DOKUMEN);

        $this->hapus($pathLama);

        return $path;
    }

    /** Menghapus berkas bila ada. Aman dipanggil dengan null. */
    public function hapus(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    /**
     * @param  array<string, string>  $mimeDiizinkan
     */
    private function simpanMentah(
        UploadedFile $berkas,
        string $folder,
        array $mimeDiizinkan,
    ): string {
        $mime = $berkas->getMimeType();

        // Lapisan terakhir: aturan validasi request sudah menyaring, tetapi
        // service ini juga dipanggil dari seeder dan perintah artisan.
        abort_unless(
            isset($mimeDiizinkan[$mime]),
            422,
            'Tipe berkas tidak diizinkan.'
        );

        $nama = Str::uuid()->toString().'.'.$mimeDiizinkan[$mime];

        return $berkas->storeAs($folder, $nama, self::DISK);
    }

    /**
     * Mengecilkan gambar dan mengubahnya menjadi WebP.
     *
     * Bila pemrosesan gagal (mis. berkas rusak sebagian atau ekstensi GD
     * bermasalah), berkas ASLI tetap dipertahankan. Gambar berukuran besar
     * masih jauh lebih baik daripada unggahan yang gagal total dan membuat
     * admin desa mengira sistemnya rusak.
     */
    private function prosesGambar(string $path): string
    {
        $absolut = Storage::disk(self::DISK)->path($path);
        $pathWebp = preg_replace('/\.\w+$/', '.webp', $path);

        if ($pathWebp === null || $pathWebp === $path) {
            $pathWebp = $path.'.webp';
        }

        $absolutWebp = Storage::disk(self::DISK)->path($pathWebp);

        try {
            $gambar = Image::load($absolut);

            // HANYA mengecilkan. Tanpa pemeriksaan ini, logo 400px akan
            // dibesarkan menjadi 1600px — berkasnya membengkak sementara
            // gambarnya justru menjadi buram.
            if ($gambar->getWidth() > self::LEBAR_MAKS) {
                $gambar->width(self::LEBAR_MAKS);
            }

            $gambar->optimize()->save($absolutWebp);
        } catch (Throwable $e) {
            Log::warning('Gagal memproses gambar, berkas asli dipertahankan: '.$e->getMessage());

            return $path;
        }

        // Berkas asli dibuang hanya setelah versi WebP benar-benar ada.
        if ($pathWebp !== $path && Storage::disk(self::DISK)->exists($pathWebp)) {
            Storage::disk(self::DISK)->delete($path);

            return $pathWebp;
        }

        return $path;
    }

    // ------------------------------------------------------------------
    // Aturan validasi siap pakai
    // ------------------------------------------------------------------

    /**
     * Aturan untuk satu berkas gambar.
     *
     * `mimetypes` (bukan `mimes`) dipakai karena ia memeriksa MIME hasil
     * deteksi isi berkas, sementara `mimes` hanya memeriksa ekstensi.
     *
     * @return list<string>
     */
    public static function aturanGambar(bool $wajib = false): array
    {
        return array_filter([
            $wajib ? 'required' : 'nullable',
            'file',
            'mimetypes:'.implode(',', array_keys(self::MIME_GAMBAR)),
            'max:'.self::MAKS_GAMBAR_KB,
        ]);
    }

    /** @return list<string> */
    public static function aturanDokumen(bool $wajib = false): array
    {
        return array_filter([
            $wajib ? 'required' : 'nullable',
            'file',
            'mimetypes:'.implode(',', array_keys(self::MIME_DOKUMEN)),
            'max:'.self::MAKS_DOKUMEN_KB,
        ]);
    }

    /** @return array<string, string> */
    public static function pesanValidasi(string $field = 'berkas'): array
    {
        return [
            "{$field}.mimetypes" => 'Format berkas tidak didukung.',
            "{$field}.max" => 'Ukuran berkas melebihi batas yang diizinkan.',
            "{$field}.*.mimetypes" => 'Format salah satu berkas tidak didukung.',
            "{$field}.*.max" => 'Ukuran salah satu berkas melebihi batas yang diizinkan.',
        ];
    }
}
