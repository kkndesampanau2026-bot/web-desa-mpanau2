<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `villages` — PRD 8.1.
 *
 * Fase 1 berjalan single-tenant (satu baris: Desa Mpanau), namun tabel ini
 * dibuat sejak awal agar kolom village_id pada seluruh tabel domain punya
 * acuan FK yang valid. Dengan begitu aktivasi multi-tenant di fase berikutnya
 * (PRD 2.2 & 16) tidak menuntut migrasi ulang berskala besar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('slug')->unique();

            // Kode wilayah administratif Kemendagri, format xx.xx.xx.xxxx
            // (contoh Desa Mpanau: 72.10.01.2013) — PRD 3.2 & 6.17.
            $table->string('kode_wilayah', 20)->nullable()->unique();

            // Subdomain per desa, dipakai saat multi-tenant diaktifkan (PRD 16).
            $table->string('subdomain', 63)->nullable()->unique();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
    }
};
