<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kategori APBDes menurut Permendagri 20/2018 — PRD 6.4. */
class BudgetCategory extends Model
{
    public const KELOMPOK = [
        'Pendapatan',
        'Belanja',
        'Pembiayaan-Penerimaan',
        'Pembiayaan-Pengeluaran',
    ];

    protected $fillable = ['village_id', 'kelompok', 'nama', 'kode', 'urutan_tampil'];

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }
}
