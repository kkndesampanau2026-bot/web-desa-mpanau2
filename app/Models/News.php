<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Berita Desa — PRD 6.10.
 */
class News extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'news';

    protected $fillable = [
        'village_id',
        'news_category_id',
        'penulis_id',
        'judul',
        'slug',
        'ringkasan',
        'konten',
        'gambar_utama',
        'galeri_gambar',
        'status',
        'tanggal_publish',
        'meta_title',
        'meta_description',
        'og_image',
    ];

    protected function casts(): array
    {
        return [
            'galeri_gambar' => 'array',
            'tanggal_publish' => 'datetime',
            'jumlah_dilihat' => 'integer',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'news_category_id');
    }

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penulis_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(NewsTag::class, 'news_news_tag');
    }

    /**
     * Artikel yang boleh dilihat publik.
     *
     * Sebuah artikel berstatus `terjadwal` otomatis menjadi layak tayang
     * begitu waktunya tiba — tanpa memerlukan job yang mengubah statusnya.
     * Menjadikan kelayakan tayang sebagai hasil query, bukan state yang harus
     * diperbarui, menghilangkan kemungkinan artikel tersangkut di status
     * `terjadwal` gara-gara scheduler mati.
     */
    public function scopeTayang(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'terjadwal'])
            ->whereNotNull('tanggal_publish')
            ->where('tanggal_publish', '<=', now());
    }

    public function scopeTerbaru(Builder $query): Builder
    {
        return $query->orderByDesc('tanggal_publish');
    }
}
