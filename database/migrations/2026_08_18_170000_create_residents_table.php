<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `residents` — data individu penduduk (PRD 6.3 & 8.3).
 *
 * Tabel paling sensitif dalam sistem ini. Tiga hal yang menentukan bentuknya:
 *
 * 1. NIK & No. KK disimpan BERPASANGAN: kolom terenkripsi untuk ditampilkan,
 *    dan kolom hash untuk dicari/diindeks. Lihat App\Services\PiiCipher.
 *
 * 2. Kolom terenkripsi memakai `text`, bukan `string(16)`. Ciphertext Laravel
 *    jauh lebih panjang dari nilai aslinya, sehingga kolom 16 karakter akan
 *    memotong data dan membuatnya mustahil didekripsi.
 *
 * 3. Statistik TIDAK dihitung dari tabel ini secara langsung saat request.
 *    PRD 7.3 menuntut agregat berasal dari snapshot terjadwal — lihat
 *    `population_stat_snapshots`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();
            $table->foreignId('dusun_id')->nullable()->constrained('dusuns')->nullOnDelete();

            // --- Data pribadi terenkripsi (UU PDP) ---
            $table->text('nik');
            $table->text('no_kk')->nullable();

            // --- Blind index: HMAC-SHA256 (64 karakter heksadesimal) ---
            // NIK unik per desa, ditegakkan lewat hash-nya karena kolom
            // terenkripsi tidak dapat diberi unique constraint.
            $table->char('nik_hash', 64);
            $table->char('no_kk_hash', 64)->nullable();

            $table->string('nama');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->date('tanggal_lahir')->nullable();

            $table->enum('status_hubungan_kk', [
                'Kepala Keluarga', 'Istri', 'Anak', 'Lainnya',
            ])->nullable();

            // Jenjang mengacu Dapodik/BPS (PRD 6.3).
            $table->enum('pendidikan_terakhir', [
                'Tidak/Belum Sekolah',
                'Belum Tamat SD/Sederajat',
                'Tamat SD/Sederajat',
                'SLTP/Sederajat',
                'SLTA/Sederajat',
                'Diploma I/II',
                'Diploma III',
                'Diploma IV/Strata I',
                'Strata II',
                'Strata III',
            ])->nullable();

            // Teks bebas dengan master terpisah: daftar pekerjaan BPS/KBJI
            // panjang dan dapat berubah, sehingga enum akan cepat usang.
            $table->string('pekerjaan')->nullable();

            $table->enum('status_perkawinan', [
                'Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati',
            ])->nullable();

            $table->enum('agama', [
                'Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu',
                'Kepercayaan', 'Lainnya',
            ])->nullable();

            $table->enum('status_domisili', [
                'Penduduk Tetap', 'Penduduk Sementara',
            ])->default('Penduduk Tetap');

            // Dihitung otomatis dari usia/status kawin saat menyimpan (PRD 6.3),
            // namun tetap dapat dikoreksi manual oleh admin bila ada
            // pengecualian di lapangan.
            $table->boolean('status_wajib_pilih')->default(false);

            $table->enum('status_mutasi', [
                'Lahir', 'Mati', 'Pindah Masuk', 'Pindah Keluar',
            ])->nullable();
            $table->date('tanggal_mutasi')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['village_id', 'nik_hash']);
            $table->index('no_kk_hash');            // menghitung jumlah KK unik
            $table->index(['village_id', 'dusun_id']);
            $table->index(['village_id', 'jenis_kelamin']);
            $table->index('tanggal_lahir');         // pengelompokan umur
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
