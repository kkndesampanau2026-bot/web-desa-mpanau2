<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto dalam album galeri — PRD 6.13. */
class GalleryPhoto extends Model
{
    use HasFactory;

    protected $fillable = ['gallery_id', 'path', 'caption', 'alt_text', 'urutan_tampil'];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
