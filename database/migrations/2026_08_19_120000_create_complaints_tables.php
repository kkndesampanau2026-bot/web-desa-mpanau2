<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaduan Masyarakat — PRD 6.15 & 8.7.
 *
 * Isi pengaduan kerap memuat keluhan tentang tetangga, perangkat desa, atau
 * persoalan keluarga. Karena itu:
 *
 *  - Nama & nomor telepon pelapor tidak pernah tampil di endpoint publik
 *    mana pun; keduanya hanya terbaca oleh admin berwenang.
 *  - Nomor tiket memuat bagian acak (bukan nomor urut) karena ia berfungsi
 *    sebagai satu-satunya kunci akses ke halaman pelacakan.
 *  - Tidak ada endpoint yang mengembalikan daftar pengaduan ke publik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            // Format PGD-YYYYMMDD-XXXX (PRD 6.15).
            $table->string('nomor_tiket', 30)->unique();

            // --- Data pelapor (tidak pernah dipublikasikan) ---
            $table->string('nama');
            $table->string('no_telepon_wa', 30);

            // Enam kategori persis seperti situs referensi (PRD 6.15).
            $table->enum('kategori_pengaduan', [
                'Umum', 'Sosial', 'Keamanan', 'Kesehatan', 'Kebersihan', 'Permintaan',
            ]);
            $table->text('isi_pengaduan');

            $table->enum('status', ['baru', 'diproses', 'selesai', 'ditolak'])->default('baru');
            $table->text('tanggapan_admin')->nullable();
            $table->text('alasan_penolakan')->nullable();

            $table->foreignId('ditangani_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tanggal_tanggapan')->nullable();

            // IP ter-hash untuk mendeteksi banjir pengaduan dari satu sumber,
            // konsisten dengan pola pada visitor_logs & bansos_search_logs.
            $table->string('ip_hash', 64)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['village_id', 'status', 'created_at']);
        });

        Schema::create('complaint_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();

            $table->string('path');
            // Nama asli disimpan terpisah dari path: berkas di disk diberi nama
            // acak agar tidak dapat ditebak, sementara admin tetap melihat nama
            // yang dikirim pelapor.
            $table->string('nama_asli');
            $table->string('mime_type', 100);
            $table->unsignedInteger('ukuran_byte');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_attachments');
        Schema::dropIfExists('complaints');
    }
};
