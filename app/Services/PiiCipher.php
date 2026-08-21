<?php

namespace App\Services;

use RuntimeException;

/**
 * Blind index untuk data pribadi — PRD 6.3, 6.6, dan 12.2.
 *
 * Persoalan yang dipecahkan di sini:
 *
 *   PRD 12.2 mewajibkan NIK & No. KK dienkripsi at-rest. Enkripsi Laravel
 *   bersifat non-deterministik (memakai IV acak), sehingga NIK yang sama
 *   menghasilkan ciphertext berbeda setiap kali disimpan. Akibatnya kolom
 *   terenkripsi TIDAK dapat diindeks maupun dicari dengan WHERE.
 *
 *   Padahal PRD 6.6 menuntut pencarian exact match (fitur Cek Bansos) dan
 *   PRD 6.3 menuntut deduplikasi berdasarkan NIK saat impor CSV.
 *
 * Solusinya: setiap kolom PII disimpan berpasangan —
 *   `nik`      → terenkripsi (Laravel encrypted cast), untuk ditampilkan
 *   `nik_hash` → HMAC-SHA256 deterministik, terindeks, untuk dicari
 *
 * HMAC dipakai, bukan hash telanjang: NIK hanya 16 digit sehingga SHA-256
 * polos dapat dibongkar dengan brute force dalam hitungan jam. Dengan kunci
 * rahasia, penyerang yang hanya memegang dump basis data tidak dapat
 * menyusun tabel pelangi.
 */
class PiiCipher
{
    private readonly string $kunci;

    public function __construct()
    {
        $mentah = config('app.pii_hash_key');

        if (blank($mentah)) {
            throw new RuntimeException(
                'PII_HASH_KEY belum diatur. Jalankan `php artisan pii:key`.'
            );
        }

        $this->kunci = str_starts_with($mentah, 'base64:')
            ? base64_decode(substr($mentah, 7), true)
            : $mentah;

        if ($this->kunci === false || strlen($this->kunci) < 32) {
            throw new RuntimeException(
                'PII_HASH_KEY tidak valid; dibutuhkan minimal 32 byte. '
                .'Jalankan `php artisan pii:key --force`.'
            );
        }
    }

    /**
     * Blind index untuk pencarian exact match.
     *
     * Nilai dinormalkan lebih dulu (spasi & tanda pisah dibuang) supaya
     * "7210 0101 2600 0001" dan "7210010126000001" menghasilkan hash yang sama.
     */
    public function hash(?string $nilai): ?string
    {
        $bersih = $this->normalkan($nilai);

        return $bersih === null ? null : hash_hmac('sha256', $bersih, $this->kunci);
    }

    /**
     * Hash atas 4 digit terakhir NIK — dasar fitur Cek Bansos (PRD 6.6),
     * yang meminta nama lengkap + 4 digit akhir, bukan NIK penuh.
     *
     * Disimpan terpisah agar pencocokan tidak menuntut warga menyebutkan
     * seluruh NIK-nya, sekaligus tidak memaksa server mendekripsi setiap
     * baris untuk membandingkan.
     */
    public function hashEmpatDigitTerakhir(?string $nik): ?string
    {
        $bersih = $this->normalkan($nik);

        if ($bersih === null || strlen($bersih) < 4) {
            return null;
        }

        return hash_hmac('sha256', substr($bersih, -4), $this->kunci);
    }

    /**
     * Menyamarkan NIK untuk ditampilkan ke petugas berwenang —
     * mis. "7210010126000001" menjadi "721001**********".
     */
    public function samarkan(?string $nik): ?string
    {
        $bersih = $this->normalkan($nik);

        if ($bersih === null) {
            return null;
        }

        $tampil = 6;

        return strlen($bersih) <= $tampil
            ? str_repeat('*', strlen($bersih))
            : substr($bersih, 0, $tampil).str_repeat('*', strlen($bersih) - $tampil);
    }

    /**
     * Menyamarkan nama untuk hasil pencarian publik — PRD 6.6 mencontohkan
     * "Budi S***": kata pertama utuh, kata berikutnya hanya huruf awalnya.
     *
     * Tujuannya agar warga dapat mengenali dirinya sendiri, tanpa membuat
     * daftar penerima bansos menjadi terbaca utuh oleh orang lain.
     */
    public function samarkanNama(string $nama): string
    {
        $kata = preg_split('/\s+/', trim($nama)) ?: [];

        return collect($kata)
            ->map(fn (string $k, int $i) => $i === 0
                ? $k
                : mb_substr($k, 0, 1).str_repeat('*', max(mb_strlen($k) - 1, 1)))
            ->implode(' ');
    }

    /** Membuang spasi dan tanda pisah agar hash konsisten. */
    private function normalkan(?string $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        $bersih = preg_replace('/[^0-9A-Za-z]/', '', $nilai) ?? '';

        return $bersih === '' ? null : $bersih;
    }
}
