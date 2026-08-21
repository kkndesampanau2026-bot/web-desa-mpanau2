<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Indikator penyusun IDM — PRD 6.7. */
class IdmIndicator extends Model
{
    public const PIHAK_PELAKSANA = [
        'Pusat', 'Provinsi', 'Kabupaten', 'Desa', 'CSR', 'Lainnya',
    ];

    protected $fillable = [
        'idm_score_id', 'no', 'nama_indikator', 'skor', 'keterangan',
        'kegiatan_rekomendasi', 'nilai_tambah', 'pihak_pelaksana',
    ];

    protected function casts(): array
    {
        return [
            'skor' => 'float',
            'nilai_tambah' => 'float',
            'pihak_pelaksana' => 'array',
        ];
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(IdmScore::class, 'idm_score_id');
    }
}
