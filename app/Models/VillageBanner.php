<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu gambar pada hero beranda — PRD 6.17.
 *
 * Menggantikan kolom tunggal `settings.banner`. Urutannya ditentukan operator
 * lewat CMS, dan itulah urutan tayang carousel di beranda.
 */
class VillageBanner extends Model
{
    protected $fillable = ['village_id', 'path', 'judul', 'urutan_tampil'];

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }
}
