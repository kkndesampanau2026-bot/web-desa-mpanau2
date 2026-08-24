<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\DataInfografis;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Infografis publik — PRD 9.2.
 *
 * Isinya dibangun oleh service `DataInfografis`, yang sama dengan yang dipakai
 * halaman Inertia. Endpoint ini tetap ada selama sebagian layar CMS masih
 * memanggilnya lewat HTTP.
 */
class InfografisController extends Controller
{
    public function __construct(private readonly DataInfografis $infografis) {}

    /** Statistik kependudukan — PRD 6.3. */
    public function penduduk(): JsonResponse
    {
        return ApiResponse::success($this->infografis->penduduk());
    }

    /** APBDes — PRD 6.4. */
    public function apbdes(Request $request): JsonResponse
    {
        $request->validate(['tahun' => ['nullable', 'integer', 'min:2000', 'max:2100']]);

        return ApiResponse::success($this->infografis->apbdes($request->integer('tahun') ?: null));
    }

    /** Data stunting agregat — PRD 6.5. */
    public function stunting(): JsonResponse
    {
        return ApiResponse::success($this->infografis->stunting());
    }

    /** IDM beserta tabel indikator — PRD 6.7. */
    public function idm(Request $request): JsonResponse
    {
        $request->validate(['tahun' => ['nullable', 'integer', 'min:2000', 'max:2100']]);

        return ApiResponse::success($this->infografis->idm($request->integer('tahun') ?: null));
    }

    /** SDGs Desa — PRD 6.8. */
    public function sdgs(Request $request): JsonResponse
    {
        $request->validate(['tahun' => ['nullable', 'integer', 'min:2000', 'max:2100']]);

        return ApiResponse::success($this->infografis->sdgs($request->integer('tahun') ?: null));
    }
}
