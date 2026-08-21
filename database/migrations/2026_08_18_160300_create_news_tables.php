<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Berita — PRD 6.10 & 8.6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();
            $table->string('nama');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['village_id', 'slug']);
        });

        Schema::create('news_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();
            $table->string('nama');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['village_id', 'slug']);
        });

        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained('villages')->cascadeOnDelete();
            $table->foreignId('news_category_id')->nullable()
                ->constrained('news_categories')->nullOnDelete();

            // Penulis di-null-kan bila akun dihapus agar artikel tetap tayang;
            // menghapus operator tidak boleh ikut menghapus berita desa.
            $table->foreignId('penulis_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('judul');
            $table->string('slug');
            $table->text('ringkasan')->nullable();
            $table->longText('konten');           // rich text, disanitasi sebelum simpan
            $table->string('gambar_utama')->nullable();
            $table->json('galeri_gambar')->nullable();

            $table->enum('status', ['draft', 'terjadwal', 'published', 'diarsipkan'])
                ->default('draft');
            // Untuk status `terjadwal`, kolom ini menyimpan waktu tayang di masa depan.
            $table->timestamp('tanggal_publish')->nullable();

            $table->unsignedBigInteger('jumlah_dilihat')->default(0);

            // Meta SEO per artikel — PRD 6.10 & 12.4.
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('og_image')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Slug unik per desa; URL publik memakai slug, bukan id (PRD 12.4).
            $table->unique(['village_id', 'slug']);
            // Query publik dominan: artikel tayang, terbaru lebih dulu.
            $table->index(['village_id', 'status', 'tanggal_publish']);
        });

        Schema::create('news_news_tag', function (Blueprint $table) {
            $table->foreignId('news_id')->constrained('news')->cascadeOnDelete();
            $table->foreignId('news_tag_id')->constrained('news_tags')->cascadeOnDelete();

            $table->primary(['news_id', 'news_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_news_tag');
        Schema::dropIfExists('news');
        Schema::dropIfExists('news_tags');
        Schema::dropIfExists('news_categories');
    }
};
