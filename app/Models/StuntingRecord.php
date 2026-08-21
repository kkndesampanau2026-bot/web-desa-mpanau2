<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Data agregat stunting — PRD 6.5.
 *
 * Tanpa kolom identitas balita sama sekali: PRD mewajibkan data publik
 * berbentuk agregat, dan risiko data pribadi yang tidak dibawa masuk adalah
 * risiko yang tidak perlu dijaga.
 */
class StuntingRecord extends Model
{
    protected $fillable = [
        'village_id', 'dusun_id', 'periode',
        'jumlah_balita_diukur', 'jumlah_kasus_stunting', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_balita_diukur' => 'integer',
            'jumlah_kasus_stunting' => 'integer',
        ];
    }

    public function dusun(): BelongsTo
    {
        return $this->belongsTo(Dusun::class);
    }

    /** Prevalensi dalam persen; null bila belum ada balita yang diukur. */
    public function prevalensi(): ?float
    {
        return $this->jumlah_balita_diukur > 0
            ? round($this->jumlah_kasus_stunting / $this->jumlah_balita_diukur * 100, 2)
            : null;
    }
}
