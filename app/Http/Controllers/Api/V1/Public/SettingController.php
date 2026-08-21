<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\CurrentVillage;
use App\Services\PengaturanSitus;
use App\Services\VisitorTracker;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Pengaturan Umum & Statistik Kunjungan — endpoint publik (PRD 9.5).
 *
 * Isinya kini dibangun oleh `PengaturanSitus`, service yang sama dengan yang
 * dipakai middleware Inertia untuk mengisi header & footer. Endpoint ini tetap
 * ada karena beberapa layar CMS masih memanggilnya lewat HTTP.
 */
class SettingController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly PengaturanSitus $pengaturan,
        private readonly VisitorTracker $tracker,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->pengaturan->untukTataLetak());
    }

    /** Widget statistik kunjungan — 7 kategori sesuai PRD 6.16. */
    public function visitorStats(): JsonResponse
    {
        return ApiResponse::success($this->tracker->ringkasan($this->village->id()));
    }
}
