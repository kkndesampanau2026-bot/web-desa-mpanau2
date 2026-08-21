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
