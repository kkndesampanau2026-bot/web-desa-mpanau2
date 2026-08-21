<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Skor IDM per tahun — PRD 6.7. */
class IdmScore extends Model
{
    public const STATUS = [
        'Sangat Tertinggal', 'Tertinggal', 'Berkembang', 'Maju', 'Mandiri',
    ];

    protected $fillable = [
        'village_id', 'tahun', 'skor_iks', 'skor_ike', 'skor_ikl', 'skor_idm',
        'status_idm', 'target_status', 'skor_minimal_target',
        'penambahan_skor_dibutuhkan', 'publikasikan',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'skor_iks' => 'float',
            'skor_ike' => 'float',
            'skor_ikl' => 'float',
            'skor_idm' => 'float',
            'skor_minimal_target' => 'float',
            'penambahan_skor_dibutuhkan' => 'float',
            'publikasikan' => 'boolean',
        ];
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(IdmIndicator::class)->orderBy('no');
    }

    /**
     * IDM adalah rata-rata ketiga sub-indeks (rumus Kemendes PDTT).
     * Dikembalikan null bila ada sub-indeks yang belum diisi, agar tidak
     * menghasilkan angka yang tampak sah padahal datanya belum lengkap.
     */
    public function hitungKomposit(): ?float
    {
        $sub = [$this->skor_iks, $this->skor_ike, $this->skor_ikl];

        return in_array(null, $sub, true) ? null : round(array_sum($sub) / 3, 4);
    }
}
