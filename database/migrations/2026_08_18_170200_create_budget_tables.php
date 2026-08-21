<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * APBDes — PRD 6.4 & 8.4.
 *
 * Struktur mengikuti Permendagri No. 20/2018: tiga kelompok utama
 * (Pendapatan, Belanja, Pembiayaan) yang masing-masing punya sub-kategori,
 * lalu item rincian di bawahnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();
            $table->year('tahun');
            $table->enum('status', ['berjalan', 'ditutup'])->default('berjalan');
            // Hanya tahun yang dipublikasikan tampil di situs publik, sehingga
            // admin dapat menyusun anggaran tahun depan tanpa langsung terbuka.
            $table->boolean('publikasikan')->default(false);
            $table->timestamps();

            $table->unique(['village_id', 'tahun']);
        });

        Schema::create('budget_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            $table->enum('kelompok', [
                'Pendapatan',
                'Belanja',
                'Pembiayaan-Penerimaan',
                'Pembiayaan-Pengeluaran',
            ]);
            $table->string('nama');       // mis. "Pendapatan Transfer"
            $table->string('kode', 20)->nullable();   // kode rekening Permendagri
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();

            $table->index(['village_id', 'kelompok']);
        });

        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();
            $table->foreignId('budget_year_id')->constrained('budget_years')->cascadeOnDelete();
            $table->foreignId('budget_category_id')->constrained('budget_categories')->cascadeOnDelete();

            $table->string('nama_item');
            // decimal(18,2), bukan float: nilai anggaran tidak boleh mengalami
            // galat pembulatan biner. 18 digit cukup untuk ratusan triliun.
            $table->decimal('jumlah_anggaran', 18, 2)->default(0);
            $table->decimal('jumlah_realisasi', 18, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();

            $table->index(['budget_year_id', 'budget_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
        Schema::dropIfExists('budget_categories');
        Schema::dropIfExists('budget_years');
    }
};
