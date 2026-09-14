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

    /**
     * Urutan tampil baku: hierarki jabatan (Ketua di atas), lalu nama.
     *
     * Kolom `urutan_tampil` tidak lagi ikut: jabatannya sendiri sudah berupa
     * daftar pilihan tertutup, sehingga hierarkinya selalu benar tanpa satu
     * angka pun diisi tangan (docs/DEVIASI.md §C22). Sesama "Anggota" diurut
     * abjad — urutan yang dapat dijelaskan, bukan urutan input.
     *
     * Dipakai halaman publik MAUPUN daftar di CMS, supaya yang dilihat operator
     * sama persis dengan yang dilihat warga.
     */
    public function scopeUrut(Builder $query): Builder
    {
        return $query
            ->orderByRaw('FIELD(jabatan, ?, ?, ?, ?)', self::URUTAN_JABATAN)
            ->orderBy('nama');
    }

    public function scopeTampil(Builder $query): Builder
    {
        return $query->where('status_aktif', true)->urut();
    }
}
