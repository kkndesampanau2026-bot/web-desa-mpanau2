<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Bentuk baku response JSON untuk seluruh endpoint /api/v1.
 *
 * Ditetapkan di Fase 1 dan dipakai sejak modul pertama supaya klien React
 * (Public App & Admin App) dapat menangani sukses, galat, dan pagination
 * dengan satu jalur kode saja — bukan menebak bentuk payload per modul.
 *
 * Bentuk sukses  : { success: true, message: string|null, data: mixed, meta?: {...} }
 * Bentuk galat   : { success: false, message: string, errors?: {...} }
 *
 * `meta` hanya muncul pada response yang memang berhalaman, sehingga
 * konsumen tahu keberadaannya menandakan ada halaman berikutnya.
 */
class ApiResponse
{
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function error(
        string $message,
        int $status = 400,
        ?array $errors = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Membungkus hasil paginasi. PRD 12.1 mewajibkan pagination pada seluruh
     * endpoint listing, jadi helper ini yang dipakai — bukan mengembalikan
     * objek paginator Laravel mentah yang bentuknya berbeda dari kontrak di atas.
     */
    public static function paginated(
        LengthAwarePaginator|ResourceCollection $paginator,
        ?string $message = null,
    ): JsonResponse {
        if ($paginator instanceof ResourceCollection) {
            $paginator = $paginator->resource;
        }

        return self::success(
            data: $paginator->items(),
            message: $message,
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
