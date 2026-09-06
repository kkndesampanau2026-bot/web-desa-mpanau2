<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu langkah pada riwayat pengajuan surat.
 *
 * Append-only: `updated_at` sengaja dimatikan agar tidak ada jalur sah untuk
 * mengubah baris yang sudah tertulis.
 */
class LetterApprovalLog extends Model
{
    public const UPDATED_AT = null;

    /** Aksi yang tercatat; sebagian tampil sebagai lini masa bagi warga. */
    public const DIAJUKAN = 'diajukan';

    public const DRAFT_DIBUAT = 'draft_dibuat';

    public const DISETUJUI_RT = 'disetujui_rt';

    public const DITOLAK_RT = 'ditolak_rt';

    public const DISETUJUI_KADUS = 'disetujui_kadus';

    public const DITOLAK_KADUS = 'ditolak_kadus';

    public const SURAT_TERBIT = 'surat_terbit';

    public const PDF_DIUNDUH = 'pdf_diunduh';

    /**
     * Berkas dibuka di peramban tanpa diunduh.
     *
     * Dicatat terpisah dari PDF_DIUNDUH, bukan disamakan dengannya: keduanya
     * sama-sama akses terhadap dokumen berisi data pribadi, tetapi menyebut
     * "diunduh" untuk berkas yang hanya dilihat membuat jejaknya berbohong.
     *
     * Tidak muncul pada lini masa warga — tahapannya daftar tetap (lihat
     * `Pages/Publik/Surat/status.ts`), dan membuka berkas bukan tahap proses.
     */
    public const PDF_DILIHAT = 'pdf_dilihat';

    public const NOTIFIKASI_GAGAL = 'notifikasi_gagal';

    protected $fillable = [
        'letter_request_id', 'letter_official_id', 'role', 'action',
        'actor_nama', 'reason', 'telegram_chat_id',
    ];

    /** @var list<string> */
    protected $hidden = ['telegram_chat_id'];

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(LetterRequest::class, 'letter_request_id');
    }

    public function pejabat(): BelongsTo
    {
        return $this->belongsTo(LetterOfficial::class, 'letter_official_id');
    }
}
