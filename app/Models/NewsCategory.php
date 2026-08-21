<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kategori berita — PRD 6.10. */
class NewsCategory extends Model
{
    use HasFactory;

    protected $fillable = ['village_id', 'nama', 'slug'];

    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }
}
