<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `village_profiles` — PRD 6.1 & 8.2.
 *
 * Satu baris per desa (relasi one-to-one dengan `villages`). Seluruh blok
 * konten halaman /profil disimpan di sini, bukan dipecah per blok, karena
 * admin selalu menyuntingnya sebagai satu formulir utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('village_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained('villages')->cascadeOnDelete();

            // --- Blok naratif (rich text, disanitasi sebelum simpan) ---
            $table->text('sambutan')->nullable();
            $table->string('foto_kepala_desa')->nullable();
            $table->string('nama_kepala_desa')->nullable();
            $table->longText('sejarah')->nullable();
            $table->text('visi')->nullable();

            // Daftar poin misi. JSON dipilih daripada tabel terpisah karena
            // misi selalu dibaca & ditulis sebagai satu kesatuan dan tidak
            // pernah menjadi acuan relasi lain.
            $table->json('misi')->nullable();

            // --- Bagan struktur (PRD 6.1) ---
            // Opsional: bila kosong, frontend menyusun bagan dari data SOTK.
            $table->string('bagan_pemerintahan')->nullable();
            $table->string('bagan_bpd')->nullable();

            // --- Data geografis (PRD 6.1) ---
            $table->unsignedBigInteger('luas_desa_m2')->nullable();
            // Diisi manual bila desa belum memakai modul Kependudukan;
            // bila kosong, nilai ditarik dari agregat residents (Fase 3).
            $table->unsignedInteger('jumlah_penduduk_manual')->nullable();
            $table->string('batas_utara')->nullable();
            $table->string('batas_timur')->nullable();
            $table->string('batas_selatan')->nullable();
            $table->string('batas_barat')->nullable();

            // --- Peta lokasi kantor desa ---
            // decimal(10,7): presisi ~1 cm, jauh melebihi kebutuhan penanda
            // kantor desa, dan bebas dari galat pembulatan tipe float.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();

            $table->unique('village_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('village_profiles');
    }
};
