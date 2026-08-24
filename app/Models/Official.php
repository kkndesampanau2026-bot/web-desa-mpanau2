<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aparat Pemerintah Desa — PRD 6.2.
 */
class Official extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id',
        'nama',
        'jabatan',
        'foto',
        'no_sk_pengangkatan',
        'periode_mulai',
        'periode_selesai',
        'urutan_tampil',
        'tingkat',
        'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'periode_mulai' => 'date',
            'periode_selesai' => 'date',
            'status_aktif' => 'boolean',
            'urutan_tampil' => 'integer',
            'tingkat' => 'integer',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /**
     * Yang tampil di halaman publik: hanya aparat aktif, diurutkan menurut
     * tingkat lebih dulu (agar bagan tersusun dari atas ke bawah) baru urutan
     * di dalam tingkat yang sama.
     */
    public function scopeTampil(Builder $query): Builder
    {
        return $query->where('status_aktif', true)
            ->orderBy('tingkat')
            ->orderBy('urutan_tampil')
            ->orderBy('nama');
    }
}
