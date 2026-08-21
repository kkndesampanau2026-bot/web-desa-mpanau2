<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail — PRD 8.1 & 12.2.
 *
 * Bersifat append-only: log ditulis lewat ActivityLogger dan hanya dibaca
 * pada halaman /admin/log-aktivitas. Tidak disediakan jalur update/delete
 * agar jejak audit tak dapat dimanipulasi dari dalam aplikasi.
 */
class ActivityLog extends Model
{
    protected $fillable = [
        'village_id',
        'user_id',
        'user_nama',
        'user_email',
        'aksi',
        'subjek_tipe',
        'subjek_id',
        'deskripsi',
        'data_sebelum',
        'data_sesudah',
        'ip_hash',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'data_sebelum' => 'array',
            'data_sesudah' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
