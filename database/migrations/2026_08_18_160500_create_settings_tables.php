<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Pengaturan Umum — PRD 6.17 & 8.8.
 *
 * `settings` dirancang sebagai satu baris berkolom (bukan key-value) karena
 * seluruh field sudah diketahui dari PRD dan disunting sebagai satu formulir.
 * Kolom bertipe tegas membuat validasi dan pembacaan jauh lebih jelas
 * dibanding tabel key-value yang semuanya bertipe string.
 *
 * Dua daftar yang panjangnya bebas (nomor telepon penting & sosial media)
 * tetap dipisah ke tabelnya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained('villages')->cascadeOnDelete();

            $table->string('logo')->nullable();
            $table->string('nama_desa')->nullable();
            $table->string('kode_wilayah', 20)->nullable();

            // Struktur wilayah administratif lengkap.
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kabupaten')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kode_pos', 10)->nullable();

            $table->text('alamat_kantor')->nullable();

            // Jam kerja per hari — bentuknya {senin: {buka, tutup, libur}, ...}.
            // JSON dipilih karena PRD menyebut jam dapat berbeda tiap hari,
            // sementara strukturnya tetap dan tidak pernah di-query per hari.
            $table->json('jam_kerja')->nullable();

            $table->string('telepon', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp', 30)->nullable();

            $table->timestamps();

            $table->unique('village_id');
        });

        Schema::create('important_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();
            $table->string('nama_layanan');     // Puskesmas, Polsek, Damkar, Ambulans, dst.
            $table->string('nomor', 30);
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();
        });

        Schema::create('social_media_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();
            $table->string('platform', 50);     // Facebook, Instagram, YouTube, TikTok, dst.
            $table->string('url');
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_links');
        Schema::dropIfExists('important_phone_numbers');
        Schema::dropIfExists('settings');
    }
};
