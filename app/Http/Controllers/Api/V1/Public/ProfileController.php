<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\ProfilDesa;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Profil Desa & SOTK — endpoint publik (PRD 9.1).
 *
 * Isinya dibangun oleh service `ProfilDesa`, yang sama dengan yang dipakai
 * halaman Inertia. Endpoint ini tetap ada selama sebagian layar CMS masih
 * memanggilnya lewat HTTP.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly ProfilDesa $profil) {}

    public function profil(): JsonResponse
    {
        return ApiResponse::success($this->profil->data());
    }

    /** Aparat Pemerintah Desa — PRD 6.2. */
    public function officials(): JsonResponse
    {
        return ApiResponse::success($this->profil->aparat());
    }

    /** Anggota BPD — lembaga terpisah dari aparat desa (PRD 3.2 & 6.2). */
    public function bpdMembers(): JsonResponse
    {
        return ApiResponse::success($this->profil->bpd());
    }
}
