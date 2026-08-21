<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Skor SDGs Desa — PRD 6.8 (18 tujuan versi Kemendes PDTT). */
class SdgsScore extends Model
{
    protected $fillable = [
        'village_id', 'tahun', 'goal_number', 'nama_goal',
        'skor', 'deskripsi_capaian', 'publikasikan',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'goal_number' => 'integer',
            'skor' => 'float',
            'publikasikan' => 'boolean',
        ];
    }
}
