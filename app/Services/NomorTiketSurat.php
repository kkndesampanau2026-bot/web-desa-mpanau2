<?php

namespace App\Services;

use App\Models\LetterRequest;
use RuntimeException;

/**
 * Pembuat nomor tiket Surat Pengantar — SP-MPN-XXXXXXXX.
 *
 * Mengikuti pola `Complaint::buatNomorTiket()` (acak, bukan berurutan, dengan
 * pemeriksaan tabrakan), dengan tiga perbedaan yang disengaja:
 *
 * 1. **Tanpa tanggal.** Nomor pengaduan memuat YYYYMMDD, yang berarti separuh
 *    nomornya dapat ditebak siapa pun yang tahu kira-kira kapan surat
 *    diajukan. Untuk surat pengantar — yang memuat NIK, alamat, dan
 *    keperluan — bagian yang dapat ditebak itu dihapus seluruhnya.
 *
 * 2. **Alfabet Crockford Base32 tanpa huruf ambigu** (tanpa I, L, O, U).
 *    Nomor tiket dibacakan lewat telepon dan disalin ulang dengan tangan;
 *    membedakan "0" dari "O" dan "1" dari "I" adalah sumber kesalahan yang
 *    dapat dihindari sejak awal. U dibuang agar tidak membentuk kata kasar
 *    secara kebetulan.
 *
 * 3. **8 karakter, bukan 4 digit.** 32^8 = 1,1 triliun kemungkinan. Bersama
 *    pembatasan laju pada halaman cek status, menebaknya menjadi tidak
 *    praktis — dan itu penting karena nomor tiket adalah kunci pertama
 *    menuju data pribadi pemohon.
 */
class NomorTiketSurat
{
    private const ALFABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const PANJANG = 8;

    private const AWALAN = 'SP-MPN-';

    /** Batas percobaan sebelum menyerah; tabrakan berturut-turut menandakan
     *  sumber acak yang rusak, bukan sekadar sial. */
    private const MAKS_PERCOBAAN = 10;

    public function buat(): string
    {
        for ($i = 0; $i < self::MAKS_PERCOBAAN; $i++) {
            $nomor = self::AWALAN.$this->acak();

            // Unique constraint pada kolom tetap menjadi penjaga terakhir;
            // pemeriksaan ini hanya agar tabrakan tidak muncul sebagai galat
            // basis data di hadapan warga.
            if (! LetterRequest::where('ticket_number', $nomor)->exists()) {
                return $nomor;
            }
        }

        throw new RuntimeException(
            'Gagal membuat nomor tiket unik setelah '.self::MAKS_PERCOBAAN.' percobaan.'
        );
    }

    private function acak(): string
    {
        $hasil = '';

        for ($i = 0; $i < self::PANJANG; $i++) {
            // random_int, bukan rand/mt_rand: nomor tiket adalah rahasia,
            // sehingga sumber acaknya harus aman secara kriptografis.
            $hasil .= self::ALFABET[random_int(0, strlen(self::ALFABET) - 1)];
        }

        return $hasil;
    }

    /**
     * Menormalkan masukan warga sebelum dicari.
     *
     * Warga menyalin nomor tiket dari WhatsApp atau menuliskannya ulang:
     * huruf kecil, spasi tersisip, dan tanda hubung yang hilang adalah hal
     * lumrah. Tanpa normalisasi, tiket yang benar dijawab "tidak ditemukan".
     */
    public static function normalkan(string $masukan): string
    {
        $bersih = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $masukan) ?? '');

        if (str_starts_with($bersih, 'SPMPN')) {
            $bersih = substr($bersih, 5);
        }

        return $bersih === '' ? '' : self::AWALAN.$bersih;
    }
}
