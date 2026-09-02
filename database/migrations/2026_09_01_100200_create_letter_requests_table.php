<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `letter_requests` — pengajuan Surat Pengantar RT/Dusun.
 *
 * Tiga keputusan yang membentuk tabel ini:
 *
 * 1. **NIK disimpan berpasangan**, persis pola `residents`: kolom terenkripsi
 *    untuk dicetak ke surat, dan blind index HMAC untuk dicari/dideduplikasi
 *    tanpa mendekripsi seluruh tabel. Lihat App\Services\PiiCipher.
 *
 * 2. **`uuid` terpisah dari `ticket_number`.** Nomor tiket dipegang warga;
 *    UUID yang masuk ke `callback_data` tombol Telegram. Memakai satu nilai
 *    untuk keduanya berarti nomor tiket warga ikut tercetak pada riwayat chat
 *    pejabat dan pada log Bot API pihak ketiga.
 *
 * 3. **`rt_id` & `dusun_id` sebagai foreign key**, bukan teks. Keduanya yang
 *    menentukan Telegram siapa yang menerima permohonan dan tanda tangan
 *    siapa yang dibubuhkan; menyimpannya sebagai teks bebas membuat salah
 *    ketik berujung surat ditandatangani pejabat yang keliru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()
                ->constrained('villages')->cascadeOnDelete();

            // Dipegang warga. Acak, bukan berurutan — lihat NomorTiketSurat.
            $table->string('ticket_number', 24)->unique();

            // Dipakai callback Telegram & URL internal.
            $table->uuid('uuid')->unique();

            // --- Data pemohon ---
            $table->text('nik');                 // terenkripsi (cast encrypted)
            $table->char('nik_hash', 64);        // blind index, terindeks
            $table->string('nama');
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir');
            $table->string('pekerjaan');
            $table->string('agama', 32);
            $table->string('status_perkawinan', 32);
            $table->string('warga_negara', 8);
            $table->text('alamat');
            $table->text('maksud_keperluan');

            $table->foreignId('rt_id')->constrained('rts')->restrictOnDelete();
            $table->foreignId('dusun_id')->constrained('dusuns')->restrictOnDelete();

            // --- Alur persetujuan ---
            $table->string('status', 32)->default('MENUNGGU_APPROVAL_RT');

            // Menunjuk letter_officials, bukan users: yang menyetujui adalah
            // Ketua RT/Kadus lewat Telegram, yang memang tidak punya akun CMS.
            $table->foreignId('approved_rt_by')->nullable()
                ->constrained('letter_officials')->nullOnDelete();
            $table->timestamp('approved_rt_at')->nullable();

            $table->foreignId('approved_kadus_by')->nullable()
                ->constrained('letter_officials')->nullOnDelete();
            $table->timestamp('approved_kadus_at')->nullable();

            $table->text('rejection_reason')->nullable();
            $table->string('rejected_by', 20)->nullable();   // RT | KADUS
            $table->foreignId('rejected_by_official_id')->nullable()
                ->constrained('letter_officials')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();

            // --- Berkas ---
            // Draft ditimpa PDF final setelah seluruh persetujuan tuntas;
            // keduanya berbagi satu kolom karena hanya satu yang pernah sah
            // untuk diunduh warga pada satu waktu.
            $table->string('pdf_path')->nullable();
            $table->string('nomor_surat')->nullable();

            // Nomor surat mengikuti format "301/RT.20/DUSUN 04" pada blangko
            // desa: urutannya per RT, bukan per desa.
            $table->unsignedInteger('nomor_urut')->nullable();

            // Hash IP, sepola dengan `complaints` — cukup untuk mengenali
            // banjir pengajuan dari satu sumber tanpa menyimpan identitas
            // jaringan warga.
            $table->string('ip_hash', 64)->nullable();

            $table->timestamps();

            $table->index(['village_id', 'status']);
            $table->index(['village_id', 'rt_id', 'status']);
            $table->index(['village_id', 'dusun_id', 'status']);
            // Deteksi pengajuan berulang: NIK sama + masih berjalan.
            $table->index(['nik_hash', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_requests');
    }
};
