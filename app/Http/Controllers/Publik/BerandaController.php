<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Models\News;
use App\Services\CurrentVillage;
use App\Services\ProfilDesa;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda — PRD Bagian 4 no. 1.
 *
 * Hero berisi identitas & visi desa, disusul pintasan modul dan tiga berita
 * terbaru. Identitas desa sendiri tidak diambil di sini: ia sudah ikut sebagai
 * prop bersama untuk header/footer, jadi mengambilnya lagi hanya menggandakan
 * query yang sama.
 */
class BerandaController extends Controller
{
    /** Jumlah berita yang tampil di beranda. */
    private const BERITA_TERBARU = 3;

    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ProfilDesa $profil,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Publik/Beranda', [
            'profil' => $this->profil->data(),

            'berita_terbaru' => News::query()
                ->where('village_id', $this->village->id())
                ->tayang()
                ->with(['kategori', 'penulis'])
                ->terbaru()
                ->take(self::BERITA_TERBARU)
                ->get()
                ->map(fn ($item) => (new NewsResource($item))->resolve())
                ->all(),
        ]);
    }
}
