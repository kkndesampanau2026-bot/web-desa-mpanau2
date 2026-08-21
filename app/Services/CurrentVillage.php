<?php

namespace App\Services;

use App\Models\Village;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Penentu desa (tenant) yang sedang dilayani.
 *
 * Fase 1–6 berjalan single-tenant, sehingga selalu mengembalikan desa aktif
 * pertama. Seluruh controller memanggil kelas ini alih-alih meng-hardcode
 * `village_id = 1`, sehingga saat multi-tenant diaktifkan (PRD 16) hanya
 * kelas ini yang perlu diubah — misalnya menjadi resolusi berdasarkan
 * subdomain — bukan puluhan controller.
 */
class CurrentVillage
{
    private const KUNCI_CACHE = 'current_village';

    public function get(): Village
    {
        $village = Cache::remember(
            self::KUNCI_CACHE,
            3600,
            fn () => Village::where('is_active', true)->orderBy('id')->first()
        );

        if (! $village) {
            throw new RuntimeException(
                'Belum ada desa aktif pada tabel `villages`. Jalankan `php artisan db:seed`.'
            );
        }

        return $village;
    }

    public function id(): int
    {
        return $this->get()->id;
    }

    public function lupakanCache(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }
}
