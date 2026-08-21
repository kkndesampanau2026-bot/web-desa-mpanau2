<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto produk UMKM — PRD 6.12. */
class ProductPhoto extends Model
{
    protected $fillable = ['product_id', 'path', 'alt_text', 'urutan_tampil'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
