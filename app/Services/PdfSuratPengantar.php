<?php

namespace App\Services;

use App\Models\LetterOfficial;
use App\Models\LetterRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pembuat berkas PDF Surat Pengantar.
 *
 * Disimpan pada disk PRIVAT, sepola dengan LampiranPengaduanService dan
 * BERBEDA dari MediaService. Surat pengantar memuat NIK, alamat, dan
 * keperluan pemohon; bila diletakkan di `storage/app/public` yang tertaut ke
 * web root, seluruh isinya dapat diunduh siapa pun yang berhasil menebak nama
 * berkasnya. Satu-satunya jalan mengambilnya adalah lewat controller yang
 * memverifikasi kepemilikan tiket.
 *
 * Tanda tangan hanya ikut tercetak pada versi FINAL. Draft sengaja dibuat
 * tanpa tanda tangan sama sekali: berkas yang beredar sebelum disetujui tidak
 * boleh pernah tampak seperti surat sah.
 */
class PdfSuratPengantar
{
    public const DISK = 'local';

    public const DIREKTORI = 'surat-pengantar';

    public function __construct(private readonly PengaturanSitus $pengaturan) {}

    /** Draft tanpa tanda tangan, dibuat segera setelah warga mengirim form. */
    public function buatDraft(LetterRequest $permohonan): ?string
    {
        return $this->render($permohonan, final: false);
    }

    /** Versi final dengan tanda tangan RT & Kepala Dusun. */
    public function buatFinal(LetterRequest $permohonan): ?string
    {
        return $this->render($permohonan, final: true);
    }

    /**
     * @return string|null path relatif pada disk privat, atau null bila gagal
     */
    private function render(LetterRequest $permohonan, bool $final): ?string
    {
        try {
            $permohonan->loadMissing(['rt.dusun', 'dusun', 'penyetujuRt', 'penyetujuKadus']);

            $pdf = Pdf::loadView('surat.pengantar', [
                'p' => $permohonan,
                'desa' => $this->pengaturan->untukTataLetak(),
                'final' => $final,
                // Tanda tangan diserahkan sebagai data URI, bukan path.
                // dompdf berjalan dengan akses berkas lokal dimatikan demi
                // keamanan; data URI menghindari kebutuhan membukanya kembali.
                'ttdRt' => $final ? $this->tandaTangan($permohonan->penyetujuRt) : null,
                'ttdKadus' => $final ? $this->tandaTangan($permohonan->penyetujuKadus) : null,
            ])->setPaper('a4');

            $path = self::DIREKTORI.'/'
                .($final ? 'final' : 'draft').'-'
                .$permohonan->uuid.'-'.Str::random(12).'.pdf';

            Storage::disk(self::DISK)->put($path, $pdf->output());

            return $path;
        } catch (Throwable $e) {
            // Sepola dengan TelegramBot: kegagalan dicatat, tidak dilempar.
            // Pengajuan yang sudah tersimpan tidak boleh ikut batal hanya
            // karena berkasnya gagal dirender — PDF dapat dibuat ulang,
            // pengajuan yang hilang tidak dapat dipulihkan.
            Log::error('Gagal membuat PDF surat pengantar.', [
                'ticket' => $permohonan->ticket_number,
                'final' => $final,
                'pesan' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Membaca gambar tanda tangan menjadi data URI.
     *
     * Mengembalikan null bila pejabat tidak punya berkas tanda tangan, dan
     * itu bukan galat: surat tetap terbit dengan ruang tanda tangan kosong
     * untuk dibubuhi manual. Menggagalkan penerbitan surat hanya karena
     * berkas gambar belum diunggah akan menghentikan layanan warga demi
     * urusan administrasi internal.
     */
    private function tandaTangan(?LetterOfficial $pejabat): ?string
    {
        $path = $pejabat?->signature_path;

        if (blank($path) || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        try {
            $isi = Storage::disk(self::DISK)->get($path);
            $mime = Storage::disk(self::DISK)->mimeType($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($isi);
        } catch (Throwable $e) {
            Log::warning('Tanda tangan pejabat tidak terbaca.', [
                'pejabat_id' => $pejabat?->id,
                'pesan' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Menghapus berkas lama; aman dipanggil dengan null. */
    public function hapus(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }
}
