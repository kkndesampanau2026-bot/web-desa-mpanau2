<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Models\News;
use App\Models\NewsCategory;
use App\Services\CurrentVillage;
use App\Support\Paginasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Berita Desa — PRD 6.10.
 */
class BeritaController extends Controller
{
    /** Batas item per halaman, mencegah payload besar (PRD 12.1). */
    private const PER_HALAMAN_MAKS = 50;

    public function __construct(private readonly CurrentVillage $village) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'kategori' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:255'],
            'cari' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::PER_HALAMAN_MAKS],
        ]);

        $berita = News::query()
            ->where('village_id', $this->village->id())
            ->tayang()
            ->with(['kategori', 'penulis'])
            ->when(
                $request->string('kategori')->toString(),
                fn ($q, $slug) => $q->whereHas('kategori', fn ($k) => $k->where('slug', $slug))
            )
            ->when(
                $request->string('tag')->toString(),
                fn ($q, $slug) => $q->whereHas('tags', fn ($t) => $t->where('slug', $slug))
            )
            ->when(
                $request->string('cari')->toString(),
                // Pencarian dibatasi pada judul & ringkasan, bukan konten:
                // LIKE pada kolom longText tidak terindeks dan akan memindai
                // seluruh tabel. Pencarian full-text menyusul bila dibutuhkan.
                fn ($q, $cari) => $q->where(
                    fn ($sub) => $sub->where('judul', 'like', "%{$cari}%")
                        ->orWhere('ringkasan', 'like', "%{$cari}%")
                )
            )
            ->terbaru()
            ->paginate($request->integer('per_page', 9))
            // withQueryString: tanpa ini, menekan "halaman 2" akan membuang
            // filter kategori/pencarian yang sedang aktif.
            ->withQueryString();

        $berita->setCollection(
            $berita->getCollection()->map(fn ($item) => (new NewsResource($item))->resolve())
        );

        return Inertia::render('Publik/Berita/Index', [
            'berita' => Paginasi::bentuk($berita),
            'kategori' => $this->kategori(),
            // Dikirim balik agar kolom pencarian & filter tetap memperlihatkan
            // keadaan yang sedang berlaku setelah halaman dimuat ulang.
            'filter' => [
                'kategori' => $request->string('kategori')->toString() ?: null,
                'cari' => $request->string('cari')->toString() ?: null,
            ],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $berita = News::query()
            ->where('village_id', $this->village->id())
            ->where('slug', $slug)
            ->tayang()
            ->with(['kategori', 'penulis', 'tags'])
            ->firstOrFail();

        // Increment langsung di database, bukan lewat model save(), agar tidak
        // menimpa perubahan lain dan tidak menyentuh kolom updated_at —
        // jumlah dilihat bukan penyuntingan konten.
        //
        // Kunjungan Inertia yang hanya meminta sebagian prop tidak dihitung:
        // itu penyegaran data, bukan pembacaan artikel baru.
        if (! $request->header('X-Inertia-Partial-Data')) {
            DB::table('news')->where('id', $berita->id)->increment('jumlah_dilihat');
        }

        return Inertia::render('Publik/Berita/Detail', [
            'berita' => NewsResource::detail($berita)->resolve(),
        ]);
    }

    /**
     * Kategori beserta jumlah artikel tayang, untuk filter di sidebar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function kategori(): array
    {
        return NewsCategory::query()
            ->where('village_id', $this->village->id())
            ->withCount(['news' => fn ($q) => $q->tayang()])
            ->orderBy('nama')
            ->get()
            ->map(fn ($k) => [
                'id' => $k->id,
                'nama' => $k->nama,
                'slug' => $k->slug,
                'jumlah_berita' => $k->news_count,
            ])
            ->all();
    }
}
