<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perluasan tabel `users` — PRD 8.1.
 *
 * Menambahkan keterkaitan tenant (village_id) dan status_aktif agar akun
 * operator dapat dinonaktifkan tanpa dihapus — penting untuk audit trail
 * (PRD 12.2) yang harus tetap dapat menelusuri siapa pelaku suatu perubahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('village_id')
                ->nullable()
                ->after('id')
                ->constrained('villages')
                ->nullOnDelete();

            $table->string('no_telepon', 20)->nullable()->after('email');
            $table->boolean('status_aktif')->default(true)->after('no_telepon');
            $table->timestamp('last_login_at')->nullable()->after('status_aktif');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['village_id']);
            $table->dropColumn([
                'village_id',
                'no_telepon',
                'status_aktif',
                'last_login_at',
                'deleted_at',
            ]);
        });
    }
};
