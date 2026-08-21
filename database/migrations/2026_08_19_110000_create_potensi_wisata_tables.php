<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Potensi Desa & Wisata — PRD 6.11 & 8.6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('potentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            $table->enum('kategori', [
                'Ekonomi',
                'Pariwisata',
                'Pertanian',
                'Industri Kreatif',
                'Lingkungan/Kelestarian',
            ]);
            $table->string('judul');
            $table->string('slug');
            $table->text('deskripsi')->nullable();
            $table->string('foto')->nullable();

            // Koordinat opsional. Tautan ke points_of_interest menyusul pada
            // Fase 6 saat tabel POI dibuat; menyimpan lat/long di sini lebih
            // dulu membuat modul ini berdiri sendiri tanpa menunggu modul peta.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('status_tampil')->default(true);
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();

            $table->unique(['village_id', 'slug']);
            $table->index(['village_id', 'kategori', 'status_tampil']);
        });

        Schema::create('tourism_spots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            $table->string('nama');
            $table->string('slug');
            $table->text('deskripsi')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('alamat')->nullable();

            // Jam operasional per hari; bentuknya sama dengan settings.jam_kerja
            // agar komponen tampilannya dapat dipakai ulang.
            $table->json('jam_operasional')->nullable();

            // Harga tiket sengaja BUKAN kolom angka: PRD 6.11 menyebut nilainya
            // dapat berupa "Gratis", dan banyak desa memakai keterangan seperti
            // "Sukarela" yang tidak terwakili oleh angka.
            $table->string('harga_tiket')->nullable();

            $table->string('kontak_pengelola')->nullable();
            // Daftar fasilitas (parkir, toilet, mushola, dst.) — dibaca utuh,
            // tidak pernah di-query per elemen.
            $table->json('fasilitas')->nullable();

            $table->boolean('status_tampil')->default(true);
            $table->timestamps();

            $table->unique(['village_id', 'slug']);
            $table->index(['village_id', 'status_tampil']);
        });

        Schema::create('tourism_spot_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tourism_spot_id')->constrained('tourism_spots')->cascadeOnDelete();

            $table->string('path');
            $table->string('caption')->nullable();
            // Wajib demi aksesibilitas (PRD 12.4); nullable di level DB agar
            // impor lama tidak gagal, namun divalidasi wajib pada form CMS.
            $table->string('alt_text')->nullable();
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();

            $table->index(['tourism_spot_id', 'urutan_tampil']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tourism_spot_photos');
        Schema::dropIfExists('tourism_spots');
        Schema::dropIfExists('potentials');
    }
};
