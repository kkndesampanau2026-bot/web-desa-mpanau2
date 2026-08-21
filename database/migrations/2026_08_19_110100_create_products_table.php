<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog Produk UMKM — PRD 6.12 & 8.6.
 *
 * Cakupan modul ini adalah KATALOG saja: menampilkan produk beserta kontak
 * penjual. Fitur keranjang dan pesan checkout WhatsApp yang disebut PRD 6.12
 * TIDAK diimplementasikan atas permintaan pemilik produk — lihat DEVIASI A4.
 *
 * Karena itu tidak ada tabel pesanan, keranjang, maupun transaksi di sini:
 * data yang tidak dikumpulkan adalah data yang tidak perlu dijaga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();
            $table->foreignId('dusun_id')->nullable()->constrained('dusuns')->nullOnDelete();

            $table->string('nama_produk');
            $table->string('slug');
            $table->string('kategori')->nullable();   // makanan, kerajinan, pertanian, dst.
            $table->text('deskripsi')->nullable();

            // decimal, bukan float: harga tidak boleh terkena galat pembulatan.
            $table->decimal('harga', 15, 2)->nullable();
            $table->string('satuan', 50)->nullable(); // pcs, kg, porsi, dst.

            // Indikator ketersediaan, bukan manajemen inventori (PRD 6.12).
            $table->boolean('tersedia')->default(true);

            // --- Data pelaku UMKM ---
            // Ini kontak usaha yang memang dimaksudkan untuk diumumkan, bukan
            // data pribadi warga seperti pada modul Kependudukan/Bansos.
            $table->string('nama_penjual');
            $table->string('kontak_wa', 30)->nullable();
            $table->string('alamat_penjual')->nullable();

            $table->boolean('status_tampil')->default(true);   // moderasi admin
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['village_id', 'slug']);
            $table->index(['village_id', 'status_tampil', 'kategori']);
        });

        Schema::create('product_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'urutan_tampil']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_photos');
        Schema::dropIfExists('products');
    }
};
