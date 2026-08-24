<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Layanan Mandiri — laman pusat layanan warga.
 *
 * Bukan modul data tersendiri, melainkan halaman pengarah (hub) yang
 * mengumpulkan seluruh layanan mandiri publik yang alurnya sudah ada
 * (pengaduan, permohonan informasi PPID, cek penerima bansos) dalam satu
 * pintu masuk yang mudah ditemukan dari navigasi utama.
 */
class LayananMandiriController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Publik/LayananMandiri');
    }
}
