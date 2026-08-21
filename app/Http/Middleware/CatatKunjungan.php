<?php

namespace App\Http\Middleware;

use App\Services\CurrentVillage;
use App\Services\VisitorTracker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Mencatat kunjungan halaman publik — PRD 6.16.
 *
 * Dipasang pada grup route publik, bukan dipanggil dari tiap controller,
 * supaya modul yang ditambahkan di fase berikutnya otomatis ikut terhitung
 * tanpa perlu diingat-ingat oleh pengembang.
 */
class CatatKunjungan
{
    public function __construct(
        private readonly VisitorTracker $tracker,
        private readonly CurrentVillage $village,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Dicatat SETELAH response dibuat, dan hanya untuk kunjungan yang
        // berhasil — permintaan yang berujung galat bukan kunjungan halaman.
        if ($request->isMethod('GET') && $response->getStatusCode() < 400) {
            try {
                $this->tracker->catat($request, $this->village->id());
            } catch (Throwable $e) {
                // Kegagalan pencatatan statistik TIDAK boleh menjatuhkan
                // halaman yang sudah berhasil dirender bagi pengunjung.
                Log::warning('Gagal mencatat kunjungan: '.$e->getMessage());
            }
        }

        return $response;
    }
}
