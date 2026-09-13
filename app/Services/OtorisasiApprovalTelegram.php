<?php

namespace App\Services;

use App\Models\LetterOfficial;
use App\Models\LetterRequest;

/**
 * Rantai pemeriksaan wewenang untuk setiap callback Telegram.
 *
 * Premis yang mendasari kelas ini: **tombol Telegram bukan bukti wewenang.**
 * `callback_data` dapat diteruskan, disalin, atau dikirim ulang oleh siapa
 * pun yang pernah melihat pesannya, dan Bot API dengan senang hati
 * meneruskannya. Yang benar-benar mengikat hanyalah chat ID pengirim — nilai
 * yang ditetapkan Telegram sendiri dan tidak dapat dipalsukan klien.
 *
 * Urutan pemeriksaannya:
 *
 *     chat ID  →  pejabat terdaftar?
 *              →  masih aktif?
 *              →  rolenya sesuai tahap yang diminta?
 *              →  RT/dusunnya sesuai pengajuan?
 *              →  status pengajuan masih menunggu tahap itu?
 *
 * Setiap tahap mengembalikan alasan gagalnya sendiri, supaya pejabat menerima
 * pesan yang benar-benar menjelaskan — bukan "gagal" tanpa keterangan.
 */
class OtorisasiApprovalTelegram
{
    public function __construct(private readonly CurrentVillage $village) {}

    /**
     * Memilih baris pejabat yang tepat bagi chat ID pengirim.
     *
     * Satu chat ID dapat menunjuk LEBIH DARI SATU baris pejabat: seseorang
     * dapat menjabat Ketua RT pada dua RT sekaligus, dan karena `rt_id` tunggal
     * per baris, ia terdaftar dua kali dengan akun Telegram yang sama (lihat
     * migrasi 2026_09_13 yang melepas keunikan kolom itu). Karena itu yang
     * dicari di sini bukan "pejabat milik chat ID ini", melainkan **baris milik
     * chat ID ini yang berwenang atas pengajuan INI**.
     *
     * Null berarti pengirim tidak terdaftar sama sekali — tidak berhak
     * menyentuh apa pun, termasuk membaca detail pengajuan.
     *
     * Urutan pemilihannya penting justru ketika tidak ada baris yang cocok
     * sepenuhnya, sebab baris yang terpilihlah yang menentukan bunyi pesan
     * penolakan di `tolakDengan()`:
     *
     *   1. role sesuai tahap + aktif + wilayah cocok  → diizinkan
     *   2. role sesuai tahap + wilayah cocok          → "akun tidak aktif"
     *   3. role sesuai tahap                          → "bukan wilayah Anda"
     *   4. baris apa pun miliknya                     → "bukan kewenangan Anda"
     *
     * Tanpa urutan ini, seorang Ketua RT yang memegang dua RT dan menekan
     * tombol untuk RT keduanya akan dibalas "pengajuan ini bukan dari wilayah
     * Anda" — hanya karena baris yang kebetulan terambil lebih dulu adalah
     * RT yang satunya.
     */
    public function pejabat(string $chatId, ?LetterRequest $permohonan, string $tahap): ?LetterOfficial
    {
        $kandidat = LetterOfficial::with(['rt.dusun', 'dusun'])
            ->where('telegram_chat_id', $chatId)
            ->get();

        if ($kandidat->isEmpty() || $permohonan === null) {
            return $kandidat->first();
        }

        $seRole = $kandidat->where('role', $this->roleUntuk($tahap));

        return $seRole->first(fn (LetterOfficial $o) => $o->berwenangAtas($permohonan))
            ?? $seRole->first(fn (LetterOfficial $o) => $o->wilayahSesuai($permohonan))
            ?? $seRole->first()
            ?? $kandidat->first();
    }

    public function permohonan(string $uuid): ?LetterRequest
    {
        return LetterRequest::with(['rt.dusun', 'dusun', 'penyetujuRt', 'penyetujuKadus'])
            ->where('village_id', $this->village->id())
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Memeriksa apakah pejabat boleh bertindak pada tahap tertentu.
     *
     * @param  string  $tahap  RT | KADUS
     * @return string|null pesan penolakan, atau null bila diizinkan
     */
    public function tolakDengan(
        ?LetterOfficial $pejabat,
        ?LetterRequest $permohonan,
        string $tahap,
    ): ?string {
        if ($pejabat === null) {
            return 'Akun Telegram Anda tidak terdaftar sebagai pejabat penanda tangan. '
                .'Hubungi operator desa.';
        }

        if (! $pejabat->is_active) {
            return 'Akun Anda sudah tidak aktif sebagai pejabat penanda tangan.';
        }

        if ($permohonan === null) {
            return 'Pengajuan tidak ditemukan atau sudah dihapus.';
        }

        if ($pejabat->role !== $this->roleUntuk($tahap)) {
            return 'Tahap ini bukan kewenangan Anda.';
        }

        // Pemeriksaan wilayah: Ketua RT 01 tidak boleh menyetujui surat warga
        // RT 20 hanya karena tombolnya diteruskan ke chat-nya.
        if (! $pejabat->berwenangAtas($permohonan)) {
            return 'Pengajuan ini bukan dari wilayah Anda.';
        }

        $statusDiharap = $tahap === 'RT'
            ? LetterRequest::MENUNGGU_RT
            : LetterRequest::MENUNGGU_KADUS;

        if ($permohonan->status !== $statusDiharap) {
            return match ($permohonan->status) {
                LetterRequest::DISETUJUI => 'Surat ini sudah terbit dan tidak dapat diubah lagi.',
                LetterRequest::DITOLAK => 'Pengajuan ini sudah ditolak sebelumnya.',
                LetterRequest::MENUNGGU_KADUS => 'Anda sudah menyetujui pengajuan ini. '
                    .'Sekarang menunggu Kepala Dusun.',
                default => 'Pengajuan ini sudah tidak menunggu persetujuan Anda.',
            };
        }

        return null;
    }

    /** Role yang berwenang pada sebuah tahap. */
    private function roleUntuk(string $tahap): string
    {
        return $tahap === 'RT'
            ? LetterOfficial::ROLE_KETUA_RT
            : LetterOfficial::ROLE_KEPALA_DUSUN;
    }

    /**
     * Membaca `callback_data` menjadi [aksi, tahap, uuid].
     *
     * Formatnya sengaja sempit dan diperiksa dengan regex: apa pun yang tidak
     * persis cocok ditolak, tanpa pernah menjadi query basis data. Nilai ini
     * datang dari luar dan diperlakukan demikian.
     *
     * @return array{0: string, 1: string, 2: string}|null [approve|reject, RT|KADUS, uuid]
     */
    public static function bacaCallback(string $data): ?array
    {
        $pola = '/^(approve|reject)_(rt|kadus):'
            .'([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/';

        if (! preg_match($pola, $data, $cocok)) {
            return null;
        }

        return [$cocok[1], strtoupper($cocok[2]), $cocok[3]];
    }
}
