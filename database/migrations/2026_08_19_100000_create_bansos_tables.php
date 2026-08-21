<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bantuan Sosial — PRD 6.6 & 8.5.
 *
 * Modul dengan permukaan risiko privasi tertinggi pada sistem ini, karena
 * memiliki fitur pencarian yang terbuka untuk publik. Tiga keputusan skema
 * yang menahan risiko itu:
 *
 * 1. NIK disimpan terenkripsi + blind index, sama seperti `residents`.
 *
 * 2. Ada kolom hash TERSENDIRI untuk 4 digit terakhir NIK. PRD 6.6 meminta
 *    pencarian publik memakai nama + 4 digit akhir (bukan NIK penuh), dan
 *    tanpa kolom ini pencocokan menuntut server mendekripsi seluruh baris
 *    setiap kali ada yang mencari.
 *
 * 3. `bansos_search_logs` mencatat POLA pencarian untuk mendeteksi scraping,
 *    tetapi tidak pernah menyimpan NIK maupun nama yang dicari — log
 *    keamanan tidak boleh berubah menjadi salinan data yang ia lindungi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bansos_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            $table->string('nama');          // BLT Dana Desa, PKH, BPNT, PIP, dst.
            $table->string('slug');
            $table->text('deskripsi')->nullable();
            $table->string('sumber_dana')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();

            $table->unique(['village_id', 'slug']);
        });

        Schema::create('bansos_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();
            $table->foreignId('bansos_type_id')->constrained('bansos_types')->cascadeOnDelete();
            $table->foreignId('dusun_id')->nullable()->constrained('dusuns')->nullOnDelete();

            $table->string('nama');
            // Terenkripsi — panjang ciphertext jauh melebihi 16 karakter.
            $table->text('nik');
            $table->text('no_kk')->nullable();

            // Blind index NIK penuh: untuk deduplikasi & pencarian admin.
            $table->char('nik_hash', 64);
            // Blind index 4 digit terakhir: khusus pencarian publik (PRD 6.6).
            $table->char('nik4_hash', 64)->nullable();

            $table->year('tahun_anggaran');
            $table->enum('status', ['aktif', 'nonaktif', 'dicabut'])->default('aktif');
            // Nominal dapat disembunyikan dari publik (PRD 6.6) — besaran
            // bantuan yang diterima seseorang bukan informasi yang perlu
            // diketahui tetangganya.
            $table->decimal('nominal', 15, 2)->nullable();
            $table->boolean('nominal_publik')->default(false);
            $table->text('keterangan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Satu orang boleh menerima beberapa jenis bantuan pada tahun sama,
            // tetapi tidak boleh tercatat dua kali untuk bantuan yang sama.
            $table->unique(
                ['village_id', 'bansos_type_id', 'tahun_anggaran', 'nik_hash'],
                'penerima_unik_per_bantuan'
            );

            // Pencarian publik menyaring nama + 4 digit; indeks ini yang
            // membuatnya tetap cepat tanpa memindai seluruh tabel.
            $table->index(['village_id', 'nik4_hash']);
            $table->index(['village_id', 'tahun_anggaran', 'status']);
        });

        Schema::create('bansos_search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            // IP ter-hash, bukan mentah — konsisten dengan visitor_logs.
            $table->string('ip_hash', 64);
            $table->boolean('ditemukan')->default(false);
            $table->timestamps();

            // Tidak ada kolom untuk nama maupun NIK yang dicari. Yang dipantau
            // hanyalah frekuensi dan tingkat keberhasilan per IP, yang sudah
            // cukup untuk mengenali pola enumerasi.
            $table->index(['village_id', 'ip_hash', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bansos_search_logs');
        Schema::dropIfExists('bansos_recipients');
        Schema::dropIfExists('bansos_types');
    }
};
