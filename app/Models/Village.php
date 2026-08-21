<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Desa (tenant) — PRD 8.1.
 *
 * Fase 1 hanya berisi satu baris. Lihat catatan multi-tenant pada migrasi
 * `create_villages_table`.
 */
class Village extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'slug',
        'kode_wilayah',
        'subdomain',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function dusuns(): HasMany
    {
        return $this->hasMany(Dusun::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
