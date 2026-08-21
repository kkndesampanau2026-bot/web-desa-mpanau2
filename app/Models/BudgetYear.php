<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tahun anggaran APBDes — PRD 6.4. */
class BudgetYear extends Model
{
    protected $fillable = ['village_id', 'tahun', 'status', 'publikasikan'];

    protected function casts(): array
    {
        return ['tahun' => 'integer', 'publikasikan' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }
}
