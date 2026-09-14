<?php

namespace Database\Factories;

use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Village>
 */
class VillageFactory extends Factory
{
    protected $model = Village::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /*
         * Tiga kolom pada tabel ini unique: `slug`, `kode_wilayah`, dan
         * `subdomain`. Nomor urut faker menjaga slug tetap berbeda antarbaris;
         * dua kolom lainnya dibiarkan null, sebab NULL tidak pernah dianggap
         * bentrok oleh unique index MySQL dan tidak satu pun pengujian
         * membutuhkan nilainya.
         */
        $nomor = $this->faker->unique()->numberBetween(1, 999999);
        $nama = $this->faker->city();

        return [
            'nama' => "Desa {$nama}",
            'slug' => Str::slug("desa-{$nama}-{$nomor}"),
            'kode_wilayah' => null,
            'subdomain' => null,
            // Aktif secara bawaan: `CurrentVillage` hanya memilih desa aktif,
            // sehingga desa uji yang non-aktif membuat seluruh halaman publik
            // melempar RuntimeException alih-alih gagal pada hal yang diuji.
            'is_active' => true,
        ];
    }

    /** Desa yang sudah dinonaktifkan. */
    public function nonaktif(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
