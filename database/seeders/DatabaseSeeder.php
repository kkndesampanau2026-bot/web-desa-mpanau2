<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Urutan penting: role & permission harus ada sebelum akun di-assign role,
        // dan desa harus ada sebelum akun diikat ke village_id.
        $this->call([
            RolePermissionSeeder::class,
            VillageSeeder::class,
            AdminUserSeeder::class,
            // Master RT & dusun untuk layanan Surat Pengantar. Bukan data
            // contoh: ia dibutuhkan agar formulir warga punya pilihan RT,
            // sehingga tetap dijalankan di produksi.
            SuratPengantarSeeder::class,
            // Kategori berita juga data master: tanpanya formulir CMS tidak
            // punya pilihan kategori sama sekali.
            KategoriBeritaSeeder::class,
            // Konten contoh untuk pengembangan; otomatis dilewati di produksi.
            KontenDemoSeeder::class,
            DataTransparansiDemoSeeder::class,
            BansosPpidDemoSeeder::class,
            EkonomiDemoSeeder::class,
            PetaDemoSeeder::class,
        ]);
    }
}
