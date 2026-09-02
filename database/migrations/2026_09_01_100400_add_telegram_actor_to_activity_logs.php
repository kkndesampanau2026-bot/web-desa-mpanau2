<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah identitas pelaku non-sesi pada `activity_logs`.
 *
 * Sampai kini setiap baris audit selalu punya `user_id`: seluruh aksi
 * sensitif berasal dari operator yang login di CMS. Persetujuan Surat
 * Pengantar memutus asumsi itu — pelakunya Ketua RT atau Kepala Dusun yang
 * menekan tombol di Telegram dan tidak punya akun CMS sama sekali.
 *
 * Tanpa kolom ini, seluruh persetujuan tercatat sebagai "pelaku: —" dan jejak
 * audit kehilangan justru bagian yang paling perlu dipertanggungjawabkan.
 *
 * Aditif dan nullable: baris audit lama tetap sah apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('pelaku_tipe', 32)->nullable()->after('user_email');
            $table->unsignedBigInteger('pelaku_id')->nullable()->after('pelaku_tipe');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn(['pelaku_tipe', 'pelaku_id']);
        });
    }
};
