<?php

namespace App\Models;

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
     * Warga lazim menuliskan nomor sebagai "0812-3456-7890" atau
     * "+62 812 3456 7890"; keduanya harus menghasilkan tautan yang sama.
     * Dinormalkan saat ditampilkan, bukan saat disimpan, agar admin tetap
     * melihat nomor persis seperti yang ia masukkan.
     */
    public function whatsappInternasional(): ?string
    {
        if (blank($this->kontak_wa)) {
            return null;
        }

        $angka = preg_replace('/\D/', '', $this->kontak_wa) ?? '';

        if ($angka === '') {
            return null;
        }

        // 08xx -> 628xx; 8xx -> 628xx; 62xx dibiarkan.
        if (str_starts_with($angka, '0')) {
            return '62'.substr($angka, 1);
        }

        return str_starts_with($angka, '62') ? $angka : '62'.$angka;
    }
}
