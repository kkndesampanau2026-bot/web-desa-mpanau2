<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Nomor telepon penting desa — PRD 6.17. */
class ImportantPhoneNumber extends Model
{
    protected $fillable = ['village_id', 'nama_layanan', 'nomor', 'urutan_tampil'];
}
