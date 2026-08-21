<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `population_stat_snapshots` — PRD 6.3, 7.3 & 8.3.
 *
 * Menyimpan hasil agregasi kependudukan yang sudah jadi, sehingga halaman
 * publik tidak pernah melakukan GROUP BY atas tabel `residents` saat request.
 * Dua manfaat sekaligus:
 *
 *  - Performa: target <500ms (PRD 12.1) tetap terjaga meski jumlah penduduk
 *    bertambah.
 *  - Privasi: endpoint publik membaca tabel agregat ini, bukan tabel berisi
 *    data pribadi — pemisahan yang membuat kebocoran lewat query publik
 *    menjadi mustahil secara struktur, bukan sekadar karena filter di kode.
 *
 * Riwayat per periode juga memungkinkan perbandingan "dari tahun ke tahun".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('population_stat_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            // Penanda periode, mis. "2026-08" (bulanan) — agregat disimpan
            // per periode agar tersedia data historis.
            $table->string('periode', 7);

            $table->unsignedInteger('total_penduduk')->default(0);
            $table->unsignedInteger('total_kk')->default(0);
            $table->unsignedInteger('total_laki')->default(0);
            $table->unsignedInteger('total_perempuan')->default(0);
            $table->unsignedInteger('total_wajib_pilih')->default(0);

            // Rincian per dimensi (umur, dusun, pendidikan, pekerjaan,
            // perkawinan, agama). JSON dipilih karena bentuk tiap rincian
            // berbeda dan hanya dibaca utuh oleh frontend, tidak pernah
            // di-query per elemen.
            $table->json('breakdown');

            $table->timestamps();

            $table->unique(['village_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('population_stat_snapshots');
    }
};
