<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto destinasi wisata — PRD 6.11. */
class TourismSpotPhoto extends Model
{
    protected $fillable = ['tourism_spot_id', 'path', 'caption', 'alt_text', 'urutan_tampil'];

    public function spot(): BelongsTo
    {
        return $this->belongsTo(TourismSpot::class, 'tourism_spot_id');
    }
}
