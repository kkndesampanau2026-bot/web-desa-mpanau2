<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Banner beranda menjadi banyak gambar, bukan satu.
 *
 * Sebelumnya hero beranda memakai satu kolom `settings.banner`. Satu foto
 * membuat beranda terasa mati begitu pengunjung kembali untuk kedua kalinya,
 * sementara desa punya lebih dari satu pemandangan yang layak ditampilkan.
 *
 * Dipisah ke tabelnya sendiri, sepola `important_phone_numbers` dan
 * `social_media_links`: daftar yang panjangnya bebas tidak pernah dijejalkan
 * sebagai kolom pada `settings`.
 *
 * `path` menyimpan PATH RELATIF pada disk `public` (sepola `logo` dan kolom
 * `banner` yang digantikannya), bukan URL penuh, supaya alamat situs dapat
 * berubah tanpa membuat seluruh baris lama menunjuk domain yang salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('village_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained('villages')->cascadeOnDelete();
            $table->string('path');
            /*
             * Keterangan singkat yang tampil di atas gambar sekaligus menjadi
             * `alt`-nya. Boleh kosong: banner adalah hiasan, dan memaksa
             * operator mengarang teks untuk foto pemandangan justru
             * menghasilkan alt yang tidak membantu siapa pun.
             */
            $table->string('judul')->nullable();
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();

            $table->index(['village_id', 'urutan_tampil']);
        });

        /*
         * Banner yang sudah diunggah perangkat desa dipindahkan, bukan
         * dibiarkan. Tanpa langkah ini, migrasi ini akan mengosongkan hero
         * beranda pada setiap pemasangan yang sudah berjalan — kegagalan yang
         * hanya terlihat setelah deploy.
         */
        $lama = DB::table('settings')
            ->whereNotNull('banner')
            ->where('banner', '!=', '')
            ->get(['village_id', 'banner']);

        foreach ($lama as $baris) {
            DB::table('village_banners')->insert([
                'village_id' => $baris->village_id,
                'path' => $baris->banner,
                'urutan_tampil' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        /*
         * Kolom lamanya DIBIARKAN ada, tetapi tidak lagi dibaca siapa pun.
         *
         * Menghapusnya membuat migrasi ini tidak dapat dibalik tanpa
         * kehilangan data, sementara biayanya nihil. `Setting::$fillable`
         * sudah tidak memuatnya lagi, jadi tidak ada jalan menulisinya.
         */
    }

    public function down(): void
    {
        // Banner pertama dikembalikan ke kolom lama agar hero tetap terisi
        // setelah rollback.
        $pertama = DB::table('village_banners')
            ->orderBy('village_id')
            ->orderBy('urutan_tampil')
            ->get(['village_id', 'path'])
            ->unique('village_id');

        foreach ($pertama as $baris) {
            DB::table('settings')
                ->where('village_id', $baris->village_id)
                ->update(['banner' => $baris->path]);
        }

        Schema::dropIfExists('village_banners');
    }
};
