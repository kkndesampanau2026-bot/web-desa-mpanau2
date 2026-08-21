<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `officials` — aparat Pemerintah Desa (PRD 6.2 & 8.2).
 *
 * Dipisah dari `bpd_members` karena keduanya lembaga berbeda dengan struktur
 * jabatan sendiri, dan PRD 3.2 menuntut keduanya tampil sebagai dua bagan
 * terpisah pada halaman Profil Desa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('officials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            $table->string('nama');
            // Teks bebas, bukan enum: nomenklatur jabatan perangkat desa
            // (Kaur, Kasi, Kadus, dst.) berbeda antar daerah dan dapat berubah
            // mengikuti peraturan bupati setempat.
            $table->string('jabatan');
            $table->string('foto')->nullable();
            $table->string('no_sk_pengangkatan')->nullable();
            $table->date('periode_mulai')->nullable();
            $table->date('periode_selesai')->nullable();
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();

            // Daftar publik selalu difilter status_aktif lalu diurutkan.
            $table->index(['village_id', 'status_aktif', 'urutan_tampil']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officials');
    }
};
