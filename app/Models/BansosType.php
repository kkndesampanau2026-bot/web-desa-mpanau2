<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Master jenis bantuan sosial — PRD 6.6. Dikonfigurasi bebas oleh admin. */
class BansosType extends Model
{
    protected $fillable = [
        'village_id', 'nama', 'slug', 'deskripsi',
        'sumber_dana', 'status_aktif', 'urutan_tampil',
    ];

    protected function casts(): array
    {
        return ['status_aktif' => 'boolean'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BansosRecipient::class);
    }
}
