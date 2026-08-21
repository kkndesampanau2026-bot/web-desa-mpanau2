<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penyimpanan lampiran pengaduan — PRD 6.15 & 12.2.
 *
 * Unggahan dari publik adalah permukaan serangan paling langsung pada sistem
 * ini. Empat keputusan yang menahannya:
 *
 * 1. **Disk privat.** Lampiran TIDAK disimpan di `storage/app/public` yang
 *    tertaut ke web root. Berkas hanya dapat diambil lewat endpoint admin
 *    yang memeriksa permission — bukan lewat URL langsung yang bocor begitu
 *    seseorang menebak namanya.
 *
 * 2. **Nama berkas diacak.** Nama asli dari pelapor tidak pernah dipakai
 *    sebagai nama berkas di disk; ia bisa memuat karakter jebakan
 *    (`../`, null byte) atau ekstensi ganda seperti `foto.jpg.php`.
 *
 * 3. **MIME diperiksa dari isi berkas**, bukan dari ekstensi maupun header
 *    Content-Type yang dikirim klien — keduanya dikendalikan penyerang.
 *
 * 4. **Ekstensi ditentukan ulang oleh server** berdasarkan MIME yang
 *    terdeteksi, sehingga berkas PHP yang menyamar jadi JPEG tetap tersimpan
 *    sebagai .jpg dan tidak akan pernah dieksekusi.
 */
class LampiranPengaduanService
{
    /** Disk privat — tidak dapat diakses langsung dari web. */
    public const DISK = 'local';

    public const DIREKTORI = 'lampiran-pengaduan';

    /**
     * MIME yang diterima beserta ekstensi yang DIPAKSAKAN server.
     *
     * @var array<string, string>
     */
    public const MIME_DIIZINKAN = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public const MAKS_BERKAS = 3;

    /** Batas ukuran per berkas dalam kilobyte (PRD 6.15 mencontohkan 5 MB). */
    public const MAKS_UKURAN_KB = 5120;

    /**
     * @param  list<UploadedFile>  $berkas
     * @return int jumlah lampiran yang tersimpan
     */
    public function simpan(Complaint $pengaduan, array $berkas): int
    {
        $tersimpan = 0;

        foreach (array_slice($berkas, 0, self::MAKS_BERKAS) as $file) {
            if (! $file->isValid()) {
                continue;
            }

            // MIME dibaca dari isi berkas (finfo), bukan dari yang diakui klien.
            $mime = $file->getMimeType();

            if (! isset(self::MIME_DIIZINKAN[$mime])) {
                continue;
            }

            $ekstensi = self::MIME_DIIZINKAN[$mime];
            $namaBerkas = Str::uuid()->toString().'.'.$ekstensi;

            $path = $file->storeAs(self::DIREKTORI, $namaBerkas, self::DISK);

            ComplaintAttachment::create([
                'complaint_id' => $pengaduan->id,
                'path' => $path,
                // Nama asli hanya disimpan sebagai teks untuk ditampilkan ke
                // admin; ia tidak pernah dipakai menyusun path apa pun.
                'nama_asli' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $mime,
                'ukuran_byte' => $file->getSize(),
            ]);

            $tersimpan++;
        }

        return $tersimpan;
    }

    /** Menghapus berkas fisik beserta catatannya. */
    public function hapus(ComplaintAttachment $lampiran): void
    {
        Storage::disk(self::DISK)->delete($lampiran->path);
        $lampiran->delete();
    }

    /**
     * Aturan validasi Laravel untuk field lampiran.
     *
     * `mimetypes` (bukan `mimes`) dipakai karena ia memeriksa MIME hasil
     * deteksi isi berkas, sementara `mimes` hanya memeriksa ekstensi.
     *
     * @return array<string, mixed>
     */
    public static function aturanValidasi(): array
    {
        return [
            'lampiran' => ['nullable', 'array', 'max:'.self::MAKS_BERKAS],
            'lampiran.*' => [
                'file',
                'mimetypes:'.implode(',', array_keys(self::MIME_DIIZINKAN)),
                'max:'.self::MAKS_UKURAN_KB,
            ],
        ];
    }

    /** @return array<string, string> */
    public static function pesanValidasi(): array
    {
        return [
            'lampiran.max' => 'Lampiran maksimal '.self::MAKS_BERKAS.' berkas.',
            'lampiran.*.mimetypes' => 'Lampiran harus berupa gambar (JPG, PNG, WebP) atau PDF.',
            'lampiran.*.max' => 'Ukuran tiap lampiran maksimal '
                .(self::MAKS_UKURAN_KB / 1024).' MB.',
        ];
    }
}
