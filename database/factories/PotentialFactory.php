<?php

namespace Database\Factories;

use App\Models\Potential;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Potential>
 */
class PotentialFactory extends Factory
{
    protected $model = Potential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $judul = $this->faker->words(3, true);

        return [
            'village_id' => Village::factory(),
            // Kolomnya ENUM: nilai di luar daftar ini ditolak basis data,
            // bukan validasi, sehingga daftarnya harus sepadan dengan migrasi
            // `create_potensi_wisata_tables`.
            'kategori' => $this->faker->randomElement([
                'Ekonomi',
                'Pariwisata',
                'Pertanian',
                'Industri Kreatif',
                'Lingkungan/Kelestarian',
            ]),
            'judul' => Str::title($judul),
            'slug' => Str::slug($judul).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'deskripsi' => $this->faker->paragraph(),
            'status_tampil' => true,
        ];
    }

    /** Potensi yang disembunyikan dari situs publik. */
    public function tersembunyi(): static
    {
        return $this->state(fn () => ['status_tampil' => false]);
    }
}
