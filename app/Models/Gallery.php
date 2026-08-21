<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Album galeri — PRD 6.13. */
class Gallery extends Model
{
    use HasFactory;

    protected $table = 'galleries';

    protected $fillable = [
        'village_id',
        'nama_album',
        'slug',
        'tanggal_kegiatan',
        'deskripsi',
        'cover_image',
        'status_tampil',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_kegiatan' => 'date',
            'status_tampil' => 'boolean',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GalleryPhoto::class)->orderBy('urutan_tampil');
    }

    public function scopeTampil(Builder $query): Builder
    {
        return $query->where('status_tampil', true)
            ->orderByDesc('tanggal_kegiatan');
    }
}
