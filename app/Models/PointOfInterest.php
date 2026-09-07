<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Titik lokasi pada peta desa — PRD 6.9. */
class PointOfInterest extends Model
{
    use HasFactory;

    protected $table = 'points_of_interest';

    /**
     * Kategori bawaan. Disimpan sebagai teks bebas di basis data agar desa
     * dapat menambahkan kategori sendiri (PRD 6.9), sementara daftar ini
     * menjadi pilihan siap pakai pada form CMS.
     */
    public const KATEGORI_BAWAAN = [
        'Kantor Pemerintahan',
        'Fasilitas Kesehatan',
        'Fasilitas Pendidikan',
        'Tempat Ibadah',
        'Wisata',
        'UMKM/Usaha',
        'Lainnya',
    ];

    protected $fillable = [
        'village_id', 'dusun_id', 'nama', 'kategori', 'deskripsi',
        'latitude', 'longitude', 'alamat', 'tautan_maps', 'foto', 'icon_marker',
        'tourism_spot_id', 'product_id', 'status_tampil',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status_tampil' => 'boolean',
        ];
    }

    public function dusun(): BelongsTo
    {
        return $this->belongsTo(Dusun::class);
    }

    public function tourismSpot(): BelongsTo
    {
        return $this->belongsTo(TourismSpot::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeTampil(Builder $query): Builder
    {
        return $query->where('status_tampil', true)->orderBy('nama');
    }
}
