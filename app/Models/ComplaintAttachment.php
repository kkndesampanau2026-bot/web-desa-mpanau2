<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lampiran pengaduan — PRD 6.15 & 8.7. */
class ComplaintAttachment extends Model
{
    protected $fillable = [
        'complaint_id', 'path', 'nama_asli', 'mime_type', 'ukuran_byte',
    ];

    protected function casts(): array
    {
        return ['ukuran_byte' => 'integer'];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
}
