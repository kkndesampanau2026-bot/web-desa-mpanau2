<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master Rukun Tetangga.
 *
 * Berada di bawah `dusuns`: relasi inilah yang membuat warga cukup memilih
 * RT-nya, sementara dusun — beserta Kepala Dusun yang akan menandatangani —
 * disimpulkan sistem.
 */
class Rt extends Model
{
    protected $fillable = [
        'village_id',
        'dusun_id',
        'nomor',
        'urutan_tampil',
    ];

    protected function casts(): array
    {
        return ['urutan_tampil' => 'integer'];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function dusun(): BelongsTo
    {
        return $this->belongsTo(Dusun::class);
    }

    public function letterOfficials(): HasMany
    {
        return $this->hasMany(LetterOfficial::class);
    }

    /** Ketua RT yang sedang menjabat, bila ada. */
    public function ketua(): ?LetterOfficial
    {
        return $this->letterOfficials()
            ->where('role', LetterOfficial::ROLE_KETUA_RT)
            ->where('is_active', true)
            ->first();
    }

    public function scopeUrut(Builder $query): Builder
    {
        return $query->orderBy('urutan_tampil')->orderBy('nomor');
    }
}
