<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `activity_logs` — PRD 8.1 & 12.2.
 *
 * Audit trail wajib untuk setiap AKSES maupun PERUBAHAN pada tabel data
 * sensitif (`residents`, `bansos_recipients`, APBDes-realisasi). Karena UU PDP
 * menuntut ketertelusuran, baris log tidak pernah dihapus bersama record
 * induknya: relasi ke subjek disimpan secara polimorfik longgar (nama tabel +
 * id) tanpa foreign key, sehingga log tetap utuh meski record aslinya dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->nullOnDelete();

            // Pelaku. nullOnDelete agar penghapusan akun tidak menghilangkan jejak audit;
            // nama & email disalin (snapshot) supaya log tetap terbaca setelah akun hilang.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_nama')->nullable();
            $table->string('user_email')->nullable();

            // Aksi: created, updated, deleted, viewed, exported, imported, login, failed_login.
            $table->string('aksi', 50)->index();

            // Subjek perubahan — sengaja TANPA FK (lihat catatan di atas).
            $table->string('subjek_tipe')->nullable();
            $table->unsignedBigInteger('subjek_id')->nullable();
            $table->string('deskripsi')->nullable();

            // Snapshot nilai sebelum/sesudah. Nilai kolom sensitif (NIK, no_kk)
            // WAJIB sudah dimasking oleh service pemanggil sebelum sampai ke sini —
            // audit trail tidak boleh menjadi jalur kebocoran data pribadi.
            $table->json('data_sebelum')->nullable();
            $table->json('data_sesudah')->nullable();

            // Konteks permintaan. IP disimpan ter-hash (bukan mentah) demi privasi,
            // konsisten dengan pola session_hash pada visitor_logs (PRD 6.16).
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->index(['subjek_tipe', 'subjek_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
