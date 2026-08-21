<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Models\News;
use App\Models\NewsCategory;
use App\Services\CurrentVillage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Berita Desa — endpoint publik (PRD 9.3).
 */
class NewsController extends Controller
{
    /** Batas item per halaman, mencegah payload besar (PRD 12.1). */
    private const PER_HALAMAN_MAKS = 50;

    public function __construct(private readonly CurrentVillage $village) {}

    public function index(Request $request): JsonResponse
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
            ->paginate($request->integer('per_page', 9));

        $berita->setCollection(
            $berita->getCollection()->map(fn ($item) => (new NewsResource($item))->resolve())
        );

        return ApiResponse::paginated($berita);
    }

    public function show(string $slug): JsonResponse
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
        DB::table('news')->where('id', $berita->id)->increment('jumlah_dilihat');

        return ApiResponse::success(NewsResource::detail($berita)->resolve());
    }

    /** Kategori beserta jumlah artikel tayang, untuk filter di sidebar. */
    public function kategori(): JsonResponse
    {
        $kategori = NewsCategory::query()
            ->where('village_id', $this->village->id())
            ->withCount(['news' => fn ($q) => $q->tayang()])
            ->orderBy('nama')
            ->get()
            ->map(fn ($k) => [
                'id' => $k->id,
                'nama' => $k->nama,
                'slug' => $k->slug,
                'jumlah_berita' => $k->news_count,
            ]);

        return ApiResponse::success($kategori);
    }
}
