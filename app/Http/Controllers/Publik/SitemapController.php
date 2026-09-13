<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\Potential;
use App\Models\Product;
use App\Models\TourismSpot;
use App\Services\CurrentVillage;
use Illuminate\Http\Response;

/**
 * Peta Situs XML (Sitemap) untuk Mesin Pencari & Answer Engines.
 *
 * Menghasilkan sitemap.xml berstandar sitemaps.org/schemas/sitemap/0.9
 * yang memuat seluruh rute publik statis dan entitas dinamis (berita, potensi, wisata, UMKM).
 */
class SitemapController extends Controller
{
    public function __construct(private readonly CurrentVillage $village) {}

    public function index(): Response
    {
        $villageId = $this->village->id();
        $baseUrl = rtrim(url('/'), '/');

        $urls = [];

        // 1. Rute Statis Utama
        $staticRoutes = [
            ['path' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
            ['path' => '/profil', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/pemerintah', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/berita', 'priority' => '0.9', 'changefreq' => 'daily'],
            ['path' => '/infografis/penduduk', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['path' => '/infografis/apbdes', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['path' => '/infografis/stunting', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/infografis/bansos', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/infografis/idm', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/infografis/sdgs', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/potensi', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['path' => '/listing', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['path' => '/ppid', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['path' => '/ppid/dasar-hukum', 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['path' => '/ppid/berkala', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['path' => '/ppid/serta-merta', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['path' => '/ppid/setiap-saat', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['path' => '/ppid/dikecualikan', 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['path' => '/layanan-mandiri', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/layanan-mandiri/surat-pengantar', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ];

        foreach ($staticRoutes as $r) {
            $urls[] = [
                'loc' => $baseUrl.$r['path'],
                'lastmod' => now()->toAtomString(),
                'changefreq' => $r['changefreq'],
                'priority' => $r['priority'],
            ];
        }

        // 2. Berita Terbit
        $berita = News::query()
            ->where('village_id', $villageId)
            ->tayang()
            ->get(['slug', 'updated_at', 'tanggal_publish']);

        foreach ($berita as $item) {
            $urls[] = [
                'loc' => $baseUrl.'/berita/'.$item->slug,
                'lastmod' => ($item->updated_at ?? $item->tanggal_publish ?? now())->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        // 3. Potensi Desa
        $potensi = Potential::query()
            ->where('village_id', $villageId)
            ->tampil()
            ->get(['slug', 'updated_at']);

        foreach ($potensi as $item) {
            $urls[] = [
                'loc' => $baseUrl.'/potensi/'.$item->slug,
                'lastmod' => ($item->updated_at ?? now())->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        // 4. Wisata Desa
        $wisata = TourismSpot::query()
            ->where('village_id', $villageId)
            ->tampil()
            ->get(['slug', 'updated_at']);

        foreach ($wisata as $item) {
            $urls[] = [
                'loc' => $baseUrl.'/potensi/'.$item->slug.'?kategori=Pariwisata',
                'lastmod' => ($item->updated_at ?? now())->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        // 5. Produk UMKM Desa
        $produk = Product::query()
            ->where('village_id', $villageId)
            ->tampil()
            ->get(['slug', 'updated_at']);

        foreach ($produk as $item) {
            $urls[] = [
                'loc' => $baseUrl.'/potensi/'.$item->slug.'?kategori=Ekonomi',
                'lastmod' => ($item->updated_at ?? now())->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;

        foreach ($urls as $url) {
            $xml .= '  <url>'.PHP_EOL;
            $xml .= '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8').'</loc>'.PHP_EOL;
            $xml .= '    <lastmod>'.$url['lastmod'].'</lastmod>'.PHP_EOL;
            $xml .= '    <changefreq>'.$url['changefreq'].'</changefreq>'.PHP_EOL;
            $xml .= '    <priority>'.$url['priority'].'</priority>'.PHP_EOL;
            $xml .= '  </url>'.PHP_EOL;
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'X-Robots-Tag' => 'noindex', // Sitemap itu sendiri tidak perlu diindeks sebagai halaman web
        ]);
    }
}
