<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\Potential;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_xml_can_be_accessed_and_has_valid_xml_structure(): void
    {
        $village = Village::factory()->create();

        // Buat dummy berita
        News::factory()->create([
            'village_id' => $village->id,
            'slug' => 'berita-uji-sitemap',
            'status' => 'published',
            'tanggal_publish' => now()->subDay(),
        ]);

        // Buat dummy potensi
        Potential::factory()->create([
            'village_id' => $village->id,
            'slug' => 'potensi-uji-sitemap',
            'status_tampil' => true,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $content = $response->getContent();
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $content);
        $this->assertStringContainsString('/berita/berita-uji-sitemap', $content);
        $this->assertStringContainsString('/potensi/potensi-uji-sitemap', $content);
        $this->assertStringContainsString('/infografis/penduduk', $content);
        $this->assertStringContainsString('/profil', $content);
    }

    /**
     * Layanan warga ikut terdaftar, di alamatnya yang sekarang.
     *
     * Formulir aduan pindah dari `/pengaduan` ke `/layanan-mandiri/aduan`
     * (DEVIASI §A5). Sitemap adalah tempat paling mudah tertinggal saat sebuah
     * alamat berpindah — tidak ada yang membukanya sehari-hari, sehingga
     * kekeliruannya baru ketahuan dari laporan Search Console berbulan-bulan
     * kemudian.
     */
    public function test_sitemap_memuat_alamat_layanan_warga_yang_berlaku(): void
    {
        Village::factory()->create();

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/layanan-mandiri/aduan', $content);
        $this->assertStringContainsString('/layanan-mandiri/surat-pengantar', $content);

        // Alamat lama hanya boleh hidup sebagai pengalihan, bukan sebagai
        // alamat yang ditawarkan sendiri kepada mesin pencari.
        $this->assertStringNotContainsString('<loc>'.url('/pengaduan').'</loc>', $content);
    }
}
