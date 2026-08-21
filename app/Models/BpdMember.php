<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anggota BPD — PRD 6.2.
 */
class BpdMember extends Model
{
    use HasFactory;

    /** Urutan jabatan dalam bagan BPD. */
    public const URUTAN_JABATAN = ['Ketua', 'Wakil Ketua', 'Sekretaris', 'Anggota'];

    protected $fillable = [
        'village_id',
        'nama',
        'jabatan',
        'foto',
        'dapil',
        'periode_mulai',
        'periode_selesai',
        'urutan_tampil',
        'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'periode_mulai' => 'date',
            'periode_selesai' => 'date',
            'status_aktif' => 'boolean',
            'urutan_tampil' => 'integer',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function scopeTampil(Builder $query): Builder
    {
        // Diurutkan menurut hierarki jabatan lebih dulu (Ketua di atas), baru
        // urutan manual — supaya bagan tetap benar meski admin lupa mengisi
        // urutan_tampil.
        return $query->where('status_aktif', true)
            ->orderByRaw(
                'FIELD(jabatan, ?, ?, ?, ?)',
                self::URUTAN_JABATAN
            )
            ->orderBy('urutan_tampil')
            ->orderBy('nama');
    }
}
