<?php

namespace App\Services;

/**
 * Nomor WhatsApp: pengenalan platform, normalisasi, dan tautan wa.me.
 *
 * Diangkat menjadi service karena aturannya kini dipakai dua modul yang tidak
 * saling kenal — katalog UMKM (nomor penjual) dan Pengaturan Umum (akun
 * WhatsApp desa pada daftar sosial media). Menyalinnya berarti dua tempat yang
 * cepat atau lambat berbeda pendapat tentang bentuk nomor yang sah.
 */
class NomorWhatsapp
{
    /**
     * Batas panjang nomor setelah diawali kode negara.
     *
     * Bawahnya menyaring salah ketik yang jelas (mis. "0812" saja); atasnya
     * mengikuti E.164. Tanpa batas ini, teks apa pun yang memuat satu angka
     * akan berubah menjadi tautan wa.me yang menuju entah ke mana.
     */
    private const MIN_DIGIT = 9;

    private const MAKS_DIGIT = 15;

    /**
     * Apakah teks platform merujuk WhatsApp.
     *
     * Kolom `platform` di CMS berupa teks bebas, jadi pencocokannya atas bentuk
     * yang sudah dinormalkan — "WhatsApp", "whatsapp", "Whats App", dan "WA"
     * sama-sama dikenali.
     */
    public static function platformWhatsapp(?string $platform): bool
    {
        $bersih = preg_replace('/[^a-z]/', '', mb_strtolower((string) $platform)) ?? '';

        return in_array($bersih, ['whatsapp', 'wa'], true);
    }

    /**
     * Nomor dalam format internasional (62…), atau null bila tidak masuk akal.
     *
     * Warga & operator lazim menuliskan nomor sebagai "0812-3456-7890" atau
     * "+62 812 3456 7890"; keduanya harus menghasilkan tujuan yang sama.
     */
    public static function internasional(?string $nomor): ?string
    {
        if (blank($nomor)) {
            return null;
        }

        $angka = preg_replace('/\D/', '', $nomor) ?? '';

        if ($angka === '') {
            return null;
        }

        $internasional = match (true) {
            str_starts_with($angka, '0') => '62'.substr($angka, 1),
            str_starts_with($angka, '62') => $angka,
            default => '62'.$angka,
        };

        $panjang = strlen($internasional);

        return $panjang >= self::MIN_DIGIT && $panjang <= self::MAKS_DIGIT
            ? $internasional
            : null;
    }

    /**
     * Tautan wa.me — dari sebuah nomor, ATAU dari tautan yang sudah jadi.
     *
     * Keduanya diterima karena operator dapat menempelkan tautan hasil
     * "bagikan" dari aplikasi WhatsApp, dan karena nilai yang TERSIMPAN memang
     * sudah berbentuk tautan: menyimpan ulang formulir tanpa menyentuh
     * kolomnya tidak boleh merusaknya.
     *
     * Tautan ke situs lain ditolak (null), meski ia memuat angka — sebuah
     * alamat Facebook yang terlanjur diketik pada baris WhatsApp lebih baik
     * ditolak daripada diam-diam menjadi nomor telepon yang keliru.
     */
    public static function tautan(?string $isian): ?string
    {
        if (blank($isian)) {
            return null;
        }

        if (preg_match('~(?:wa\.me|whatsapp\.com/send\?phone=)/?\+?(\d[\d\s\-]*)~i', $isian, $cocok)) {
            $isian = $cocok[1];
        } elseif (str_contains($isian, '://')) {
            return null;
        }

        $nomor = self::internasional($isian);

        return $nomor ? 'https://wa.me/'.$nomor : null;
    }
}
