<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Informasi publik berkala/serta-merta/setiap saat — PRD 6.14. */
class PpidInformationItem extends Model
{
    public const JENIS = ['berkala', 'serta-merta', 'setiap-saat'];

    protected $fillable = [
        'village_id', 'jenis', 'judul', 'deskripsi', 'kategori', 'periode',
        'tingkat_urgensi', 'file', 'tanggal_publish', 'status_tampil',
    ];

    protected function casts(): array
    {
        return ['tanggal_publish' => 'date', 'status_tampil' => 'boolean'];
    }

    public function scopeTampil(Builder $query): Builder
    {
        return $query->where('status_tampil', true)->orderByDesc('tanggal_publish');
    }
}
