<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `rts` — master Rukun Tetangga.
 *
 * Sampai kini sistem hanya mengenal `dusuns` sebagai pembagian wilayah; RT
 * belum pernah menjadi entitas. Surat Pengantar menuntutnya ada, sebab RT-lah
 * yang menentukan siapa penanda tangan pertama sekaligus ke Telegram siapa
 * permohonan dikirim.
 *
 * `dusun_id` bukan sekadar kolom pelengkap: RT berada DI DALAM sebuah dusun,
 * dan relasi inilah yang memungkinkan sistem menyimpulkan Kepala Dusun mana
 * yang menjadi penanda tangan kedua — warga tidak perlu memilih dusunnya
 * sendiri dan tidak bisa salah memilih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()
                ->constrained('villages')->cascadeOnDelete();

            // Dibiarkan nullable agar RT tetap dapat didata lebih dulu ketika
            // pemetaan RT-ke-dusun belum tuntas diverifikasi ke data desa.
            $table->foreignId('dusun_id')->nullable()
                ->constrained('dusuns')->nullOnDelete();

            // String, bukan integer: nomor RT lazim ditulis berimbuhan nol di
            // depan ("01", "02") dan itu ikut tercetak apa adanya pada surat.
            $table->string('nomor', 10);

            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();

            // Nomor RT unik dalam satu desa, bukan dalam satu dusun: warga
            // menyebut "RT 20" tanpa menyebut dusunnya, dan penomoran di Desa
            // Mpanau memang berjalan menerus 01–21 lintas dusun.
            $table->unique(['village_id', 'nomor']);
            $table->index(['village_id', 'urutan_tampil']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rts');
    }
};
