<?php

namespace App\Models;

use App\Services\NomorWhatsapp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Produk UMKM — PRD 6.12.
 *
 * Cakupannya katalog saja; tidak ada keranjang maupun transaksi (DEVIASI A4).
 */
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'village_id', 'dusun_id', 'nama_produk', 'slug', 'kategori', 'deskripsi',
        'harga', 'satuan', 'tersedia', 'nama_penjual', 'kontak_wa',
        'alamat_penjual', 'status_tampil',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'tersedia' => 'boolean',
            'status_tampil' => 'boolean',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function dusun(): BelongsTo
    {
        return $this->belongsTo(Dusun::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProductPhoto::class)->orderBy('urutan_tampil');
    }

    /** Hanya produk yang lolos moderasi admin yang tampil ke publik. */
    public function scopeTampil(Builder $query): Builder
    {
        return $query->where('status_tampil', true);
    }

    /**
     * Nomor WhatsApp dalam format internasional untuk tautan wa.me.
     *
     * Dinormalkan saat DITAMPILKAN, bukan saat disimpan, agar admin tetap
     * melihat nomor persis seperti yang ia masukkan. Aturannya sendiri tinggal
     * di `NomorWhatsapp` karena Pengaturan Umum memakainya juga.
     */
    public function whatsappInternasional(): ?string
    {
        return NomorWhatsapp::internasional($this->kontak_wa);
    }
}
