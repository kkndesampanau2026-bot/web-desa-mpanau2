<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rincian item APBDes — PRD 6.4. */
class BudgetItem extends Model
{
    protected $fillable = [
        'village_id', 'budget_year_id', 'budget_category_id',
        'nama_item', 'jumlah_anggaran', 'jumlah_realisasi',
        'keterangan', 'urutan_tampil',
    ];

    protected function casts(): array
    {
        // Cast decimal, bukan float: nilai rupiah tidak boleh terkena galat
        // pembulatan biner.
        return [
            'jumlah_anggaran' => 'decimal:2',
            'jumlah_realisasi' => 'decimal:2',
        ];
    }

    public function tahun(): BelongsTo
    {
        return $this->belongsTo(BudgetYear::class, 'budget_year_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'budget_category_id');
    }
}
