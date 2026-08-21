<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PPID — Pejabat Pengelola Informasi dan Dokumentasi (PRD 6.14 & 8.7).
 *
 * Struktur mengikuti UU No. 14/2008 tentang Keterbukaan Informasi Publik:
 * dasar hukum, tiga kategori informasi (berkala, serta-merta, setiap saat),
 * dan alur permohonan informasi oleh warga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppid_legal_basis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            $table->string('judul_regulasi');
            $table->string('nomor_regulasi')->nullable();
            $table->year('tahun')->nullable();
            $table->string('file_pdf')->nullable();
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();
        });

        /*
         * Tiga kategori informasi publik disatukan dalam satu tabel dan
         * dibedakan kolom `jenis`. Ketiganya punya field yang hampir sama dan
         * ditampilkan dengan tata letak serupa; memecahnya menjadi tiga tabel
         * hanya akan menggandakan kode CRUD tanpa manfaat.
         */
        Schema::create('ppid_information_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            $table->enum('jenis', ['berkala', 'serta-merta', 'setiap-saat']);
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->string('kategori')->nullable();
            $table->string('periode')->nullable();      // mis. "Triwulan I 2026"

            // Khusus informasi serta-merta: menandai tingkat kemendesakan
            // (mis. bencana) yang wajib diumumkan segera.
            $table->enum('tingkat_urgensi', ['rendah', 'sedang', 'tinggi'])->nullable();

            $table->string('file')->nullable();
            $table->date('tanggal_publish')->nullable();
            $table->boolean('status_tampil')->default(true);
            $table->timestamps();

            $table->index(['village_id', 'jenis', 'tanggal_publish']);
        });

        Schema::create('ppid_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            // Nomor registrasi untuk pelacakan tanpa login (PRD 6.14).
            $table->string('nomor_registrasi', 32)->unique();

            $table->string('nama_pemohon');
            // Nomor identitas pemohon tergolong data pribadi — dienkripsi,
            // dengan blind index untuk verifikasi saat pelacakan status.
            $table->text('no_identitas')->nullable();
            $table->char('no_identitas_hash', 64)->nullable();

            $table->string('kontak');                   // telepon/WA/email
            $table->text('alamat')->nullable();
            $table->text('informasi_diminta');
            $table->text('tujuan_penggunaan')->nullable();
            $table->enum('cara_memperoleh', ['langsung', 'email', 'pos'])->default('langsung');

            $table->enum('status', [
                'diajukan', 'diverifikasi', 'diproses', 'selesai', 'ditolak',
            ])->default('diajukan');

            $table->text('tanggapan_admin')->nullable();
            // Wajib diisi saat status `ditolak` — UU KIP mensyaratkan alasan
            // penolakan disampaikan kepada pemohon.
            $table->text('alasan_penolakan')->nullable();
            $table->string('dokumen_balasan')->nullable();

            $table->foreignId('ditangani_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tanggal_tanggapan')->nullable();

            $table->timestamps();

            $table->index(['village_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppid_requests');
        Schema::dropIfExists('ppid_information_items');
        Schema::dropIfExists('ppid_legal_basis');
    }
};
