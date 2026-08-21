<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Potensi Desa — PRD 6.11. */
class Potential extends Model
{
    use HasFactory;

    public const KATEGORI = [
        'Ekonomi',
        'Pariwisata',
        'Pertanian',
        'Industri Kreatif',
        'Lingkungan/Kelestarian',
    ];

    protected $fillable = [
        'village_id', 'kategori', 'judul', 'slug', 'deskripsi', 'foto',
        'latitude', 'longitude', 'status_tampil', 'urutan_tampil',
    ];

    protected function casts(): array
    {
        return [
            'status_tampil' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function scopeTampil(Builder $query): Builder
    {
        return $query->where('status_tampil', true)
            ->orderBy('urutan_tampil')
            ->orderBy('judul');
    }
}
