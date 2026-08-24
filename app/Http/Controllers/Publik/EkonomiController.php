<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Services\DataEkonomi;
use App\Support\Paginasi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Potensi Desa, Wisata, dan Katalog UMKM — PRD 6.11 & 6.12.
 */
class EkonomiController extends Controller
{
    public function __construct(private readonly DataEkonomi $ekonomi) {}

    public function potensi(Request $request): Response
    {
        $request->validate(['kategori' => ['nullable', 'string', 'max:100']]);
        $kategori = $request->string('kategori')->toString() ?: null;

        return Inertia::render('Publik/Ekonomi/Potensi', [
            'data' => $this->ekonomi->potensi($kategori),
            'filter' => ['kategori' => $kategori],
        ]);
    }

    public function wisata(): Response
    {
        return Inertia::render('Publik/Ekonomi/Wisata', [
            'wisata' => $this->ekonomi->wisata(),
        ]);
    }

    public function wisataDetail(string $slug): Response
    {
        return Inertia::render('Publik/Ekonomi/WisataDetail', [
            'wisata' => $this->ekonomi->wisataDetail($slug),
        ]);
    }

    public function belanja(Request $request): Response
    {
        $request->validate([
            'kategori' => ['nullable', 'string', 'max:100'],
            'cari' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $kategori = $request->string('kategori')->toString() ?: null;
        $cari = $request->string('cari')->toString() ?: null;

        $produk = $this->ekonomi
            ->produk($kategori, $cari, $request->integer('per_page', 12))
            // Tanpa ini, menekan "halaman 2" akan membuang filter kategori
            // maupun kata kunci pencarian yang sedang aktif.
            ->withQueryString();

        return Inertia::render('Publik/Ekonomi/Belanja', [
            'produk' => Paginasi::bentuk($produk),
            'kategori' => $this->ekonomi->kategoriProduk(),
            'filter' => ['kategori' => $kategori, 'cari' => $cari],
        ]);
    }

    public function produkDetail(string $slug): Response
    {
        return Inertia::render('Publik/Ekonomi/ProdukDetail', [
            'produk' => $this->ekonomi->produkDetail($slug),
        ]);
    }
}
