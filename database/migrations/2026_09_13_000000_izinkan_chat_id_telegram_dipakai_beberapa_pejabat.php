<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melonggarkan `letter_officials.telegram_chat_id` dari UNIQUE menjadi index
 * biasa.
 *
 * Migrasi pembuat tabel menjadikannya unik dengan alasan yang benar pada
 * waktunya: otorisasi callback Telegram bertumpu pada pemetaan chat ID ->
 * pejabat, dan pemetaan itu dulu diasumsikan tunggal.
 *
 * Kenyataan di desa mematahkan asumsinya — **satu orang dapat menjabat Ketua
 * RT pada dua RT sekaligus**. Karena `rt_id` tunggal per baris, orang itu
 * membutuhkan dua baris pejabat, dan keduanya menunjuk akun Telegram yang sama.
 *
 * Yang menggantikan keunikan itu sebagai penjaga wewenang: `pejabat()` pada
 * OtorisasiApprovalTelegram kini memilih di antara SELURUH baris milik chat ID
 * tersebut, yaitu baris yang role dan wilayahnya benar-benar cocok dengan
 * pengajuan yang sedang diproses. Pemeriksaan wilayah tidak melemah — ia justru
 * berpindah dari "chat ID ini milik siapa" ke "baris mana milik chat ID ini
 * yang berwenang atas surat ini".
 *
 * Index biasa tetap dipasang: pencarian chat ID terjadi pada setiap pembaruan
 * webhook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_officials', function (Blueprint $table) {
            $table->dropUnique(['telegram_chat_id']);
            $table->index('telegram_chat_id', 'letter_officials_chat_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('letter_officials', function (Blueprint $table) {
            $table->dropIndex('letter_officials_chat_id_idx');
            $table->unique('telegram_chat_id');
        });
    }
};
