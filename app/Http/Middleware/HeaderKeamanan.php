<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan respons — PRD 12.2.
 *
 * Backend ini melayani JSON, bukan halaman HTML, sehingga sebagian header
 * yang lazim (mis. CSP untuk skrip) tidak relevan di sini — proteksi itu
 * menjadi tanggung jawab server web yang menyajikan aplikasi React.
 *
 * Yang dipasang di bawah adalah header yang memang bermakna bagi respons API
 * dan unduhan berkas.
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Versi PHP tidak perlu diketahui siapa pun. Ia hanya mempermudah
        // penyerang mencocokkan kerentanan yang sudah diketahui publik.
        //
        // header_remove() dipakai, bukan $response->headers->remove(), karena
        // X-Powered-By disisipkan PHP sendiri di tingkat SAPI (expose_php) —
        // di luar jangkauan koleksi header milik Laravel.
        $response->headers->remove('X-Powered-By');

        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }

        // Melarang peramban menebak-nebak tipe berkas. Penting terutama bagi
        // unduhan lampiran pengaduan: tanpa ini, berkas yang isinya HTML dapat
        // dirender sebagai halaman di domain kita.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // API ini tidak pernah dimaksudkan tampil di dalam frame.
        $response->headers->set('X-Frame-Options', 'DENY');

        // Menahan URL lengkap (yang dapat memuat nomor tiket atau registrasi)
        // agar tidak ikut terkirim ke situs pihak ketiga lewat header Referer.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Mematikan fitur peramban yang tidak dibutuhkan API sama sekali.
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=()'
        );

        // Respons yang memuat data pribadi tidak boleh disimpan cache bersama
        // (proxy) maupun cache peramban.
        if ($this->memuatDataSensitif($request)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }

    /**
     * Jalur yang responsnya dapat memuat data pribadi.
     *
     * Mencakup seluruh endpoint admin serta halaman pelacakan publik, yang
     * meski tanpa login tetap menampilkan isi pengaduan/permohonan seseorang.
     */
    private function memuatDataSensitif(Request $request): bool
    {
        return $request->is('api/v1/admin/*')
            || $request->is('api/v1/pengaduan*')
            || $request->is('api/v1/ppid/permintaan*')
            || $request->is('api/v1/infografis/bansos/cek');
    }
}
