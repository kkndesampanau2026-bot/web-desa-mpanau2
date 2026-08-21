<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `bpd_members` — anggota Badan Permusyawaratan Desa (PRD 6.2 & 8.2).
 *
 * BPD adalah lembaga permusyawaratan yang terpisah dari perangkat desa;
 * anggotanya mewakili wilayah (dapil) tertentu, atribut yang tidak dimiliki
 * `officials`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bpd_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();

            $table->string('nama');
            $table->enum('jabatan', ['Ketua', 'Wakil Ketua', 'Sekretaris', 'Anggota'])
                ->default('Anggota');
            $table->string('foto')->nullable();
            $table->string('dapil')->nullable();   // wilayah yang diwakili
            $table->date('periode_mulai')->nullable();
            $table->date('periode_selesai')->nullable();
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();

            $table->index(['village_id', 'status_aktif', 'urutan_tampil']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpd_members');
    }
};
