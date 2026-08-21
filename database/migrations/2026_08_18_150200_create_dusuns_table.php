<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `dusuns` — PRD 8.2.
 *
 * Master dusun/RW yang menjadi acuan bagi `residents` (breakdown penduduk
 * per dusun, PRD 6.3), `stunting_records` (PRD 6.5), dan `points_of_interest`
 * (PRD 6.9). Dibuat di Fase 1 karena banyak modul fase berikutnya bergantung
 * padanya sebagai foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dusuns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')
                ->nullable()
                ->constrained('villages')
                ->cascadeOnDelete();

            $table->string('nama');
            $table->string('nama_kepala_dusun')->nullable();
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();

            // Nama dusun unik dalam satu desa, bukan unik global —
            // desa berbeda boleh memiliki nama dusun yang sama.
            $table->unique(['village_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dusuns');
    }
};
