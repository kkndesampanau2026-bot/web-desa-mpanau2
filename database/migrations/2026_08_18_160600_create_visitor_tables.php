<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statistik Kunjungan — PRD 6.16 & 8.3.
 *
 * Dua tabel dengan peran berbeda:
 *  - `visitor_logs` mencatat kunjungan mentah, ter-dedup per sesi/hari.
 *  - `visitor_daily_summary` menyimpan hasil agregasi harian, dibaca halaman
 *    publik. Tanpa pemisahan ini, widget statistik (yang tampil di SETIAP
 *    halaman) akan memicu COUNT pada tabel yang terus membesar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            $table->date('tanggal');
            // Hash dari (IP + user agent + salt harian) — IP mentah TIDAK pernah
            // disimpan, sesuai prinsip minimalisasi data pada PRD 6.16 & 12.2.
            // Salt yang berganti tiap hari mencegah pelacakan lintas hari.
            $table->string('session_hash', 64);
            $table->string('halaman')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            // Menegakkan dedup "satu kunjungan unik per sesi per hari" di level
            // database, bukan hanya di kode — agar reload halaman tidak
            // menggandakan hitungan meski ada kondisi balapan.
            $table->unique(['village_id', 'tanggal', 'session_hash'], 'visitor_unik_harian');
        });

        Schema::create('visitor_daily_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            $table->date('tanggal');
            $table->unsignedInteger('jumlah_unique_visit')->default(0);
            $table->timestamps();

            $table->unique(['village_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_daily_summary');
        Schema::dropIfExists('visitor_logs');
    }
};
