<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifikasi CAPTCHA — PRD 6.6 & 12.2.
 *
 * Memakai **Cloudflare Turnstile**: gratis tanpa batas, dan berbeda dari
 * reCAPTCHA ia tidak melacak pengunjung lintas situs maupun menuntut mereka
 * mengerjakan tugas anotasi gambar. Untuk situs pemerintah desa yang wajib
 * dapat diakses semua warga, keduanya penting.
 *
 * Bersifat OPSIONAL: bila kunci belum dikonfigurasi, verifikasi dilewati dan
 * pertahanan lain (rate limiting, pencocokan dua faktor, pesan netral) tetap
 * berjalan. Dengan begitu desa dapat memasang situsnya lebih dulu, lalu
 * menambahkan CAPTCHA setelah memperoleh kunci — tanpa formulirnya lumpuh
 * di antara kedua keadaan itu.
 */
class CaptchaVerifier
{
    private const URL_VERIFIKASI = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** Nama field token yang dikirim widget Turnstile. */
    public const FIELD = 'cf-turnstile-response';

    public function aktif(): bool
    {
        return filled(config('captcha.secret_key'));
    }

    /**
     * Memverifikasi token dari klien.
     *
     * Mengembalikan true bila CAPTCHA tidak aktif — lihat catatan kelas.
     */
    public function verifikasi(Request $request): bool
    {
        if (! $this->aktif()) {
            return true;
        }

        $token = $request->input(self::FIELD);

        if (blank($token)) {
            return false;
        }

        try {
            $respons = Http::asForm()
                ->timeout(5)
                ->post(self::URL_VERIFIKASI, [
                    'secret' => config('captcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);

            return (bool) ($respons->json('success') ?? false);
        } catch (ConnectionException $e) {
            // Layanan CAPTCHA tidak terjangkau. Permintaan DITOLAK, bukan
            // diloloskan: memilih "gagal-terbuka" akan membuat pertahanan ini
            // dapat dilumpuhkan hanya dengan mengganggu koneksi ke Cloudflare.
            //
            // Rate limiting tetap berlaku, sehingga warga yang mencoba lagi
            // beberapa saat kemudian tidak akan terkunci permanen.
            Log::warning('Verifikasi CAPTCHA gagal terhubung: '.$e->getMessage());

            return false;
        }
    }

    /** @return array<string, mixed> aturan validasi untuk field token. */
    public function aturanValidasi(): array
    {
        return $this->aktif()
            ? [self::FIELD => ['required', 'string']]
            : [];
    }
}
