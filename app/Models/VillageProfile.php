<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Profil desa — PRD 6.1.
 */
class VillageProfile extends Model
{
    protected $fillable = [
        'village_id',
        'sambutan',
        'foto_kepala_desa',
        'nama_kepala_desa',
        'sejarah',
        'visi',
        'misi',
        'bagan_pemerintahan',
        'bagan_bpd',
        'luas_desa_m2',
        'jumlah_penduduk_manual',
        'batas_utara',
        'batas_timur',
        'batas_selatan',
        'batas_barat',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'misi' => 'array',
            'luas_desa_m2' => 'integer',
            'jumlah_penduduk_manual' => 'integer',
            // decimal cast menjaga presisi koordinat; float akan menimbulkan
            // galat pembulatan pada digit terakhir.
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /** Luas desa dalam hektar — PRD 6.1 menyebut opsi tampilan hektar. */
    public function luasHektar(): ?float
    {
        return $this->luas_desa_m2 === null
            ? null
            : round($this->luas_desa_m2 / 10_000, 2);
    }
}
