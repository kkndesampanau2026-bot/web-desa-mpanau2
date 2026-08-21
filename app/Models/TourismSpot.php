<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Destinasi wisata desa — PRD 6.11. */
class TourismSpot extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id', 'nama', 'slug', 'deskripsi', 'latitude', 'longitude',
        'alamat', 'jam_operasional', 'harga_tiket', 'kontak_pengelola',
        'fasilitas', 'status_tampil',
    ];

    protected function casts(): array
    {
        return [
            'jam_operasional' => 'array',
            'fasilitas' => 'array',
            'status_tampil' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TourismSpotPhoto::class)->orderBy('urutan_tampil');
    }

    public function scopeTampil(Builder $query): Builder
    {
        return $query->where('status_tampil', true)->orderBy('nama');
    }
}
