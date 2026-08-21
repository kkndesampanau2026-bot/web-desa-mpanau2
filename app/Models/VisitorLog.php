<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Log kunjungan mentah — PRD 6.16. IP tidak pernah disimpan, hanya hash-nya. */
class VisitorLog extends Model
{
    protected $fillable = ['village_id', 'tanggal', 'session_hash', 'halaman', 'user_agent'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }
}
