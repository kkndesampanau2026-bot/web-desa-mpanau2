<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Admin melewati seluruh pemeriksaan permission. Ditangani di sini
        // (bukan dengan menyinkronkan seluruh permission ke role tersebut) agar
        // permission baru yang lahir di fase berikutnya otomatis tercakup tanpa
        // perlu menjalankan ulang seeder.
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        $this->batasiLajuPencarianBansos();
        $this->batasiLajuCekSurat();
        $this->batasiLajuSurat();
    }

    /**
     * Pembatasan laju pengajuan surat & webhook Telegram.
     *
     * Keduanya WAJIB memakai named limiter, bukan `throttle:3,1` di route.
     * Middleware throttle tanpa nama menyusun kuncinya dari domain + alamat IP
     * saja — bukan dari rutenya — sehingga SELURUH route ber-`throttle:N,1`
     * berbagi satu penghitung yang sama. Akibatnya lalu lintas webhook, yang
     * wajar mencapai puluhan panggilan per menit, ikut memakan jatah formulir
     * warga yang hanya tiga.
     *
     * Named limiter memberi masing-masing awalan kunci sendiri, sehingga
     * keduanya benar-benar terpisah.
     */
    private function batasiLajuSurat(): void
    {
        // Setiap pengajuan yang berhasil melahirkan satu render PDF dan satu
        // pesan ke ponsel Ketua RT, jadi remnya ketat.
        RateLimiter::for('kirim-surat', fn (Request $request) => Limit::perMinute(3)
            ->by($request->ip())
            ->response(fn () => back()->withErrors([
                'nama' => 'Terlalu banyak pengajuan dari perangkat ini. '
                    .'Silakan coba lagi beberapa saat lagi.',
            ])->withInput()));

        // Longgar: pengirimnya Telegram, bukan pengunjung. Batas ini hanya
        // menahan banjir permintaan palsu ke alamat webhook.
        RateLimiter::for('telegram-webhook', fn (Request $request) => Limit::perMinute(120)
            ->by($request->ip()));
    }

    /**
     * Pembatasan laju halaman Cek Status Surat & unduh PDF.
     *
     * Halaman itu membuka data pemohon (nama, RT, keperluan, dan berkas
     * suratnya) bila pasangan nomor tiket + tanggal lahir tepat. Nomor tiket
     * sudah acak 8 karakter, namun tanggal lahir hanya punya belasan ribu
     * kemungkinan yang masuk akal — cukup sedikit untuk ditelusuri satu per
     * satu bila tiketnya terlanjur diketahui.
     *
     * Rem inilah yang membuat penelusuran semacam itu memakan waktu berhari-
     * hari alih-alih beberapa menit. Dipatok per alamat IP, sepola dengan
     * limiter Cek Bansos.
     */
    private function batasiLajuCekSurat(): void
    {
        RateLimiter::for('cek-surat', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response(fn () => response(
                    'Terlalu banyak percobaan. Silakan coba lagi beberapa saat lagi.',
                    429
                ));
        });
    }

    /**
     * Pembatasan laju fitur Cek Penerima Bansos — PRD 6.6 & 15.
     *
     * Inilah pertahanan utama terhadap enumerasi: dengan nama + 4 digit NIK,
     * seorang penyerang yang memegang daftar nama warga hanya perlu mencoba
     * 10.000 kombinasi per orang. Tanpa pembatasan ini, seluruh daftar
     * penerima bantuan sedesa dapat disusun ulang dalam hitungan jam.
     *
     * Dibuat sebagai named limiter (bukan `throttle:10,1` di route) agar
     * angkanya dapat disetel lewat env tanpa menyentuh kode, dan agar
     * responsnya memakai bentuk kontrak ApiResponse.
     */
    private function batasiLajuPencarianBansos(): void
    {
        RateLimiter::for('cek-bansos', function (Request $request) {
            $maks = (int) config('bansos.rate_limit', 10);
            $menit = (int) config('bansos.rate_decay', 1);

            return Limit::perMinutes($menit, $maks)
                ->by($request->ip())
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak percobaan pencarian. '
                        .'Silakan coba lagi beberapa saat lagi.',
                ], 429));
        });
    }
}
