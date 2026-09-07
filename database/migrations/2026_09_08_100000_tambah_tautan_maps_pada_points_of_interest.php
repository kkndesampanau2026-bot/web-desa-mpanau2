<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah tautan Google Maps pada titik lokasi — PRD 6.9.
 *
 * Koordinat sudah cukup untuk MENGGAMBAR marker, tetapi tidak untuk
 * MENAVIGASI ke sana: pengunjung yang ingin berangkat harus menyalin angka
 * lintang-bujur secara manual ke aplikasi peta di ponselnya. Kolom ini
 * membuat popup marker dapat menyediakan tautan siap-klik.
 *
 * Nullable dan opsional: bila perangkat desa tidak mengisinya, tautan tetap
 * dibangkitkan dari koordinat titik itu sendiri (lihat PetaController publik),
 * sehingga seluruh titik lama langsung punya tautan tanpa perlu disunting
 * satu per satu. Kolom ini dipakai untuk kasus yang tidak bisa ditebak dari
 * koordinat — misalnya tautan Google Maps resmi milik usaha/UMKM yang sudah
 * memuat nama, foto, jam buka, dan ulasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->string('tautan_maps', 500)->nullable()->after('alamat');
        });
    }

    public function down(): void
    {
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->dropColumn('tautan_maps');
        });
    }
};
