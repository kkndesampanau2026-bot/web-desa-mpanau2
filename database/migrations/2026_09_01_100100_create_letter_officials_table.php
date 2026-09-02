<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `letter_officials` — pejabat penanda tangan surat.
 *
 * Sengaja TIDAK menumpang tabel `officials`. Keduanya tampak mirip, namun
 * menjawab pertanyaan yang berbeda:
 *
 *   officials        → "siapa yang tergambar pada bagan SOTK di /pemerintah"
 *   letter_officials → "siapa yang berwenang menandatangani surat untuk RT/
 *                       dusun tertentu, dan ke Telegram mana ia dihubungi"
 *
 * Menggabungkannya berarti 21 Ketua RT ikut muncul pada bagan struktur
 * Pemerintah Desa — `Official::scopeTampil()` menampilkan seluruh baris aktif
 * tanpa filter jabatan — padahal Ketua RT bukan perangkat desa.
 *
 * Barisnya juga tidak pernah dihapus saat pejabat berganti, melainkan
 * di-nonaktifkan (`is_active`). `letter_approval_logs` menunjuk ke baris ini,
 * sehingga surat yang sudah terbit tetap dapat ditelusuri ke orang yang
 * benar-benar menyetujuinya, bukan ke penggantinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_officials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()
                ->constrained('villages')->cascadeOnDelete();

            // KETUA_RT wajib punya rt_id; KEPALA_DUSUN wajib punya dusun_id.
            // Ditegakkan di tingkat aplikasi (LetterOfficial + FormRequest)
            // karena MySQL 8 belum menjamin CHECK constraint di semua versi
            // yang dipakai penyedia hosting desa.
            $table->string('role', 20);

            $table->foreignId('rt_id')->nullable()
                ->constrained('rts')->cascadeOnDelete();
            $table->foreignId('dusun_id')->nullable()
                ->constrained('dusuns')->cascadeOnDelete();

            $table->string('nama');

            // Teks bebas: sebagian desa menulis "Ketua RT", sebagian lain
            // "Ketua RT 20 Dusun IV". Yang tercetak pada surat adalah nilai
            // ini, sehingga desa dapat menyesuaikannya sendiri.
            $table->string('jabatan_teks')->nullable();

            /*
             * Chat ID, BUKAN username. Username Telegram dapat diganti
             * pemiliknya kapan saja dan langsung memutus tautan otorisasi;
             * chat ID bersifat tetap seumur akun.
             *
             * Unik: satu akun Telegram tidak boleh menjadi dua pejabat
             * sekaligus, karena otorisasi callback bertumpu pada pemetaan
             * chat ID -> pejabat yang tunggal.
             */
            $table->string('telegram_chat_id', 32)->nullable()->unique();

            // Path relatif pada disk PRIVAT. Tanda tangan adalah data biometrik
            // ringan — bila diletakkan di disk `public`, siapa pun yang menebak
            // namanya dapat mengunduh lalu menempelkannya ke dokumen lain.
            $table->string('signature_path')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Pencarian utama: "siapa KETUA_RT aktif untuk rt_id ini".
            $table->index(['village_id', 'role', 'rt_id', 'is_active'], 'letter_officials_rt_idx');
            $table->index(['village_id', 'role', 'dusun_id', 'is_active'], 'letter_officials_dusun_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_officials');
    }
};
