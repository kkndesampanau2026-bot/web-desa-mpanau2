<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gambar banner (hero) beranda, agar dapat diganti perangkat desa sendiri.
 *
 * Sebelumnya banner berupa berkas statis di `public/gambar/` — menggantinya
 * menuntut akses ke server, sesuatu yang tidak dimiliki operator desa.
 * Kolomnya menyimpan PATH RELATIF pada disk `public` (sepola `logo`), bukan
 * URL penuh, supaya alamat situs dapat berubah tanpa membuat seluruh baris
 * lama menunjuk ke domain yang salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('banner')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('banner');
        });
    }
};
