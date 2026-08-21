<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Agregat kunjungan harian — PRD 6.16. */
class VisitorDailySummary extends Model
{
    protected $table = 'visitor_daily_summary';

    protected $fillable = ['village_id', 'tanggal', 'jumlah_unique_visit'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah_unique_visit' => 'integer',
        ];
    }
}
