<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah kolom `tingkat` pada `officials` — metadata hierarki bagan struktur.
 *
 * Halaman Profil menggambar bagan organisasi sebagai baris bertingkat: aparat
 * dengan `tingkat` sama berjajar dalam satu baris (Kepala Desa di tingkat 0,
 * Sekretaris di tingkat 1, para Kaur/Kasi di tingkat 2, dst.), dan `urutan_tampil`
 * menentukan urutan kiri-ke-kanan di dalam baris itu. Tanpa kolom ini, daftar
 * aparat yang datar tidak dapat direpresentasikan sebagai pohon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('officials', function (Blueprint $table) {
            $table->unsignedSmallInteger('tingkat')->default(0)->after('urutan_tampil');
        });
    }

    public function down(): void
    {
        Schema::table('officials', function (Blueprint $table) {
            $table->dropColumn('tingkat');
        });
    }
};
