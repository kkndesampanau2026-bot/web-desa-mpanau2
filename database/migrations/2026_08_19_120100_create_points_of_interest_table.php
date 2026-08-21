<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titik Lokasi (Points of Interest) — PRD 6.9 & 8.6.
 *
 * Modul "Listing" pada situs referensi: peta interaktif berisi titik-titik
 * menarik di seluruh desa. Sengaja TERPISAH dari peta lokasi kantor desa pada
 * halaman Profil — keduanya melayani kebutuhan berbeda (PRD 3.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_of_interest', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();
            $table->foreignId('dusun_id')->nullable()->constrained('dusuns')->nullOnDelete();

            $table->string('nama');
            $table->string('kategori');   // teks bebas + master di kode, agar desa dapat menambah sendiri
            $table->text('deskripsi')->nullable();

            // Koordinat WAJIB: sebuah titik peta tanpa koordinat tidak punya
            // alasan untuk ada.
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->string('alamat')->nullable();
            $table->string('foto')->nullable();
            $table->string('icon_marker', 50)->nullable();

            // Tautan opsional ke record wisata/produk (PRD 6.9). Dibuat
            // nullOnDelete agar menghapus destinasi wisata tidak ikut
            // menghapus titik petanya — admin dapat menautkannya ulang.
            $table->foreignId('tourism_spot_id')->nullable()
                ->constrained('tourism_spots')->nullOnDelete();
            $table->foreignId('product_id')->nullable()
                ->constrained('products')->nullOnDelete();

            $table->boolean('status_tampil')->default(true);
            $table->timestamps();

            $table->index(['village_id', 'status_tampil', 'kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_of_interest');
    }
};
