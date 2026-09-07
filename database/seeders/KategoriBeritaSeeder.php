<?php

namespace Database\Seeders;

use App\Models\NewsCategory;
use App\Models\Village;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Kategori berita — data master, BUKAN data contoh.
 *
 * Ketiganya sebelumnya dibuat `KontenDemoSeeder`, yang otomatis dilewati di
 * produksi. Akibatnya daftar kategori di sana kosong: operator tidak punya
 * pilihan apa pun saat menulis berita, dan filter kategori di situs publik
 * tidak pernah memunculkan satu chip pun.
 *
 * Dipisahkan ke sini dan dijalankan seperti `RolePermissionSeeder` — pada
 * setiap boot, terlepas dari `JALANKAN_SEEDER` — karena isinya lebih dekat ke
 * skema daripada ke konten: idempoten, tidak menimpa berita mana pun, dan
 * tanpanya modul Berita tidak dapat dipakai sama sekali.
 */
class KategoriBeritaSeeder extends Seeder
{
    /**
     * Urutannya menentukan urutan tampil pada formulir CMS.
     *
     * @var list<string>
     */
    public const KATEGORI = ['Kegiatan Desa', 'Pembangunan', 'Pengumuman'];

    public function run(): void
    {
        foreach (Village::all() as $village) {
            foreach (self::KATEGORI as $nama) {
                // Dicocokkan lewat SLUG, bukan nama: slug itulah yang tertulis
                // pada tautan filter yang mungkin sudah dibagikan warga, jadi
                // ia tidak boleh berubah walau nama tampilnya disunting.
                NewsCategory::updateOrCreate(
                    ['village_id' => $village->id, 'slug' => Str::slug($nama)],
                    ['nama' => $nama],
                );
            }
        }
    }
}
