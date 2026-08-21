<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cache agregat kependudukan per periode — PRD 6.3 & 7.3.
 *
 * Hanya tabel INI yang dibaca endpoint publik infografis penduduk; tabel
 * `residents` tidak pernah tersentuh dari jalur publik.
 */
class PopulationStatSnapshot extends Model
{
    protected $fillable = [
        'village_id', 'periode', 'total_penduduk', 'total_kk',
        'total_laki', 'total_perempuan', 'total_wajib_pilih', 'breakdown',
    ];

    protected function casts(): array
    {
        return ['breakdown' => 'array'];
    }
}
