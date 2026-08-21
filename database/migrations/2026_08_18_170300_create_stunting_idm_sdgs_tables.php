<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stunting, IDM, dan SDGs Desa — PRD 6.5, 6.7, 6.8 & 8.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * PRD 6.5: data stunting yang dipublikasikan WAJIB berbentuk agregat.
         * Tabel ini sengaja tidak memiliki kolom identitas balita sama sekali —
         * pencatatan by-name untuk intervensi Puskesmas berada di luar cakupan
         * website profil ini, sehingga risikonya tidak dibawa masuk.
         */
        Schema::create('stunting_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();
            $table->foreignId('dusun_id')->nullable()->constrained('dusuns')->nullOnDelete();

            $table->string('periode', 7);        // mis. "2026-01" (tahun/semester)
            $table->unsignedInteger('jumlah_balita_diukur')->default(0);
            $table->unsignedInteger('jumlah_kasus_stunting')->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            // Satu baris per dusun per periode; baris tanpa dusun berarti
            // agregat tingkat desa.
            $table->unique(['village_id', 'periode', 'dusun_id'], 'stunting_periode_dusun');
        });

        /*
         * IDM — indeks komposit dari IKS, IKE, dan IKL (PRD 6.7).
         */
        Schema::create('idm_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            $table->year('tahun');
            // decimal(6,4): skor IDM Kemendes memakai 4 angka di belakang koma.
            $table->decimal('skor_iks', 6, 4)->nullable();
            $table->decimal('skor_ike', 6, 4)->nullable();
            $table->decimal('skor_ikl', 6, 4)->nullable();
            $table->decimal('skor_idm', 6, 4)->nullable();

            $table->enum('status_idm', [
                'Sangat Tertinggal', 'Tertinggal', 'Berkembang', 'Maju', 'Mandiri',
            ])->nullable();

            $table->enum('target_status', [
                'Sangat Tertinggal', 'Tertinggal', 'Berkembang', 'Maju', 'Mandiri',
            ])->nullable();
            $table->decimal('skor_minimal_target', 6, 4)->nullable();
            $table->decimal('penambahan_skor_dibutuhkan', 6, 4)->nullable();

            $table->boolean('publikasikan')->default(false);
            $table->timestamps();

            $table->unique(['village_id', 'tahun']);
        });

        Schema::create('idm_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idm_score_id')->constrained('idm_scores')->cascadeOnDelete();

            $table->unsignedSmallInteger('no')->default(0);
            $table->string('nama_indikator');
            $table->decimal('skor', 6, 4)->nullable();
            $table->text('keterangan')->nullable();
            $table->text('kegiatan_rekomendasi')->nullable();
            $table->decimal('nilai_tambah', 6, 4)->nullable();

            // Multi-pilih (Pusat/Provinsi/Kabupaten/Desa/CSR/Lainnya) — disimpan
            // sebagai JSON karena hanya dibaca utuh, tidak pernah di-query
            // per pelaksana.
            $table->json('pihak_pelaksana')->nullable();

            $table->timestamps();

            $table->index(['idm_score_id', 'no']);
        });

        /*
         * SDGs Desa — 18 tujuan versi Kemendes PDTT (17 global + 1 khusus desa),
         * PRD 6.8.
         */
        Schema::create('sdgs_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            $table->year('tahun');
            $table->unsignedTinyInteger('goal_number');   // 1–18
            $table->string('nama_goal');
            $table->decimal('skor', 6, 2)->nullable();
            $table->text('deskripsi_capaian')->nullable();

            $table->boolean('publikasikan')->default(false);
            $table->timestamps();

            $table->unique(['village_id', 'tahun', 'goal_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdgs_scores');
        Schema::dropIfExists('idm_indicators');
        Schema::dropIfExists('idm_scores');
        Schema::dropIfExists('stunting_records');
    }
};
