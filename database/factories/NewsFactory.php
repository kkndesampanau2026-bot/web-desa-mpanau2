<?php

namespace Database\Factories;

use App\Models\News;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    protected $model = News::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $judul = $this->faker->sentence(6);

        return [
            // Hanya dipakai bila pemanggilnya tidak menyebut village_id
            // sendiri; Laravel menunda pembuatannya sampai benar-benar perlu.
            'village_id' => Village::factory(),
            'judul' => $judul,
            // Slug unik per desa. Nomor urut disertakan karena dua judul acak
            // yang kebetulan sama akan melanggar unique (village_id, slug).
            'slug' => Str::slug($judul).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'ringkasan' => $this->faker->paragraph(),
            'konten' => $this->faker->paragraphs(3, true),
            // Draf secara bawaan — status tayang adalah keputusan yang harus
            // disebut eksplisit oleh pengujian yang memang mengujinya.
            'status' => 'draft',
            'tanggal_publish' => null,
        ];
    }

    /** Artikel yang sudah tayang. */
    public function tayang(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'tanggal_publish' => now()->subDay(),
        ]);
    }
}
