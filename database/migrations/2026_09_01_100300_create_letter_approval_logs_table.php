<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `letter_approval_logs` — riwayat langkah pengajuan surat.
 *
 * Melengkapi `activity_logs`, bukan menggantikannya. `activity_logs` adalah
 * jejak audit lintas modul yang dibaca operator di /admin; tabel ini adalah
 * riwayat per-pengajuan yang dibaca WARGA sebagai lini masa pada halaman cek
 * status. Menyatukan keduanya berarti halaman publik harus membaca tabel
 * audit — yang memuat jejak seluruh modul, termasuk yang tak boleh terbaca
 * publik.
 *
 * Append-only: tidak ada `updated_at`, dan tidak ada jalur ubah/hapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_approval_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('letter_request_id')
                ->constrained('letter_requests')->cascadeOnDelete();

            // Null untuk aksi yang pelakunya bukan pejabat (warga mengajukan,
            // sistem membuat PDF).
            $table->foreignId('letter_official_id')->nullable()
                ->constrained('letter_officials')->nullOnDelete();

            $table->string('role', 20)->nullable();      // WARGA | RT | KADUS | SISTEM
            $table->string('action', 40);                // diajukan | disetujui | ditolak | ...

            // Nama pejabat disalin, tidak hanya direlasikan: bila pejabat
            // dihapus dari master, lini masa surat yang sudah terbit tetap
            // menyebut siapa yang dahulu menandatanganinya.
            $table->string('actor_nama')->nullable();

            $table->text('reason')->nullable();
            $table->string('telegram_chat_id', 32)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['letter_request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_approval_logs');
    }
};
