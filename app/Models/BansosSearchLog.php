<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log pencarian publik bansos — PRD 6.6 & 8.5.
 *
 * Sengaja TIDAK memiliki kolom untuk nama maupun NIK yang dicari. Yang
 * dipantau hanya frekuensi dan tingkat keberhasilan per IP — sudah cukup
 * untuk mengenali pola enumerasi, tanpa menjadikan log ini salinan data
 * yang seharusnya ia lindungi.
 */
class BansosSearchLog extends Model
{
    protected $fillable = ['village_id', 'ip_hash', 'ditemukan'];

    protected function casts(): array
    {
        return ['ditemukan' => 'boolean'];
    }
}
