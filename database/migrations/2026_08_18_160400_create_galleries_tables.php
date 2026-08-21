<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Galeri — PRD 6.13 & 8.6.
 *
 * Foto dikelompokkan per album/kegiatan, bukan sebagai satu tumpukan datar,
 * mengikuti struktur situs referensi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            $table->string('nama_album');
            $table->string('slug');
            $table->date('tanggal_kegiatan')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('status_tampil')->default(true);
            $table->timestamps();

            $table->unique(['village_id', 'slug']);
            $table->index(['village_id', 'status_tampil', 'tanggal_kegiatan']);
        });

        Schema::create('gallery_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained('galleries')->cascadeOnDelete();

            $table->string('path');
            $table->string('caption')->nullable();
            // Wajib diisi demi aksesibilitas (PRD 12.4: alt text pada seluruh
            // gambar konten). Nullable di level DB agar impor lama tidak gagal,
            // tetapi divalidasi wajib pada form CMS.
            $table->string('alt_text')->nullable();
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();

            $table->index(['gallery_id', 'urutan_tampil']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_photos');
        Schema::dropIfExists('galleries');
    }
};
