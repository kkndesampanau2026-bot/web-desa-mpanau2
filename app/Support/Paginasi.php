<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Bentuk baku data berhalaman untuk prop Inertia.
 *
 * Sengaja TIDAK memakai `toArray()` bawaan paginator Laravel, yang membawa
 * belasan kunci termasuk URL absolut per halaman. Bentuk di bawah sama persis
 * dengan `ApiResponse::paginated()` yang sudah lama dipakai komponen React,
 * sehingga perpindahan dari fetch API ke prop Inertia tidak memaksa setiap
 * daftar menulis ulang cara membaca metadata halamannya.
 */
class Paginasi
{
    /**
     * @return array{items: array<int, mixed>, meta: array<string, int>}
     */
    public static function bentuk(LengthAwarePaginator $paginator): array
    {
        return [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
