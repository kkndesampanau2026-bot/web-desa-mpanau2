<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pengaturan umum desa — PRD 6.17. */
class Setting extends Model
{
    protected $fillable = [
        'village_id',
        'logo',
        'nama_desa',
        'kode_wilayah',
        'kelurahan',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'kode_pos',
        'alamat_kantor',
        'jam_kerja',
        'telepon',
        'email',
        'whatsapp',
    ];

    protected function casts(): array
    {
        return ['jam_kerja' => 'array'];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }
}
