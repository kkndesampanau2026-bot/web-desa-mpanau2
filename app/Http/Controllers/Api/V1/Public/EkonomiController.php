<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\DataEkonomi;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Potensi Desa, Wisata, dan Katalog UMKM — endpoint publik (PRD 9.3).
 *
 * Isinya dibangun oleh service `DataEkonomi`, yang sama dengan yang dipakai
 * halaman Inertia. Endpoint ini tetap ada selama sebagian layar CMS masih
 * memanggilnya lewat HTTP.
 */
class EkonomiController extends Controller
{
    public function __construct(private readonly DataEkonomi $ekonomi) {}

    public function potensi(Request $request): JsonResponse
    {
        $request->validate(['kategori' => ['nullable', 'string', 'max:100']]);

        return ApiResponse::success(
            $this->ekonomi->potensi($request->string('kategori')->toString() ?: null)
        );
    }

    public function potensiDetail(string $slug): JsonResponse
    {
        return ApiResponse::success($this->ekonomi->potensiDetail($slug));
    }

    public function wisata(): JsonResponse
    {
        return ApiResponse::success($this->ekonomi->wisata());
    }

    public function wisataDetail(string $slug): JsonResponse
    {
        return ApiResponse::success($this->ekonomi->wisataDetail($slug));
    }

    public function produk(Request $request): JsonResponse
    {
        $request->validate([
            'kategori' => ['nullable', 'string', 'max:100'],
            'cari' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        return ApiResponse::paginated($this->ekonomi->produk(
            $request->string('kategori')->toString() ?: null,
            $request->string('cari')->toString() ?: null,
            $request->integer('per_page', 12),
        ));
    }

    public function produkDetail(string $slug): JsonResponse
    {
        return ApiResponse::success($this->ekonomi->produkDetail($slug));
    }

    public function kategoriProduk(): JsonResponse
    {
        return ApiResponse::success($this->ekonomi->kategoriProduk());
    }
}
