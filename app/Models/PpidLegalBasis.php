<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Dasar hukum PPID — PRD 6.14. */
class PpidLegalBasis extends Model
{
    protected $table = 'ppid_legal_basis';

    protected $fillable = [
        'village_id', 'judul_regulasi', 'nomor_regulasi',
        'tahun', 'file_pdf', 'urutan_tampil',
    ];
}
