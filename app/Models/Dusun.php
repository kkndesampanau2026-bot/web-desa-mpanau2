<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master dusun/RW — PRD 8.2.
 */
class Dusun extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id',
        'nama',
        'nama_kepala_dusun',
        'urutan_tampil',
    ];

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }
}
