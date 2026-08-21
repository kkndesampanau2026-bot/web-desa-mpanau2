<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Potential;
use App\Models\Product;
use App\Models\TourismSpot;
use App\Services\CurrentVillage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Potensi Desa, Wisata, dan Katalog UMKM — endpoint publik (PRD 9.3).
 *
 * Ketiganya digabung dalam satu controller karena berbagi sifat yang sama:
 * konten promosi desa yang sepenuhnya publik, tanpa data pribadi warga dan
 * tanpa aturan kerahasiaan khusus.
 */
class EkonomiController extends Controller
{
    private const TTL = 1800;

    public function __construct(private readonly CurrentVillage $village) {}

    // ------------------------------------------------------------------
    // Potensi Desa — PRD 6.11
    // ------------------------------------------------------------------

    public function potensi(Request $request): JsonResponse
    {
        $request->validate(['kategori' => ['nullable', 'string', 'max:100']]);

        $villageId = $this->village->id();
        $kategori = $request->string('kategori')->toString();
        $kunci = "potensi:{$villageId}:".($kategori ?: 'semua');

        $data = Cache::remember($kunci, self::TTL, function () use ($villageId, $kategori) {
            $daftar = Potential::query()
                ->where('village_id', $villageId)
                ->tampil()
                ->when($kategori, fn ($q, $k) => $q->where('kategori', $k))
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'kategori' => $p->kategori,
                    'judul' => $p->judul,
                    'slug' => $p->slug,
                    'deskripsi' => $p->deskripsi,
                    'foto' => $p->foto ? asset('storage/'.$p->foto) : null,
                    'koordinat' => $p->latitude === null ? null : [
                        'latitude' => $p->latitude,
                        'longitude' => $p->longitude,
                    ],
                ]);

            if ($daftar->isEmpty()) {
                return null;   // empty-state (PRD 3.2)
            }

            return [
                'items' => $daftar,
                // Kategori yang benar-benar terisi saja — menampilkan filter
                // yang pasti kosong hanya membuat pengunjung menemui jalan buntu.
                'kategori_tersedia' => Potential::where('village_id', $villageId)
                    ->where('status_tampil', true)
                    ->distinct()
                    ->orderBy('kategori')
                    ->pluck('kategori'),
            ];
        });

        return ApiResponse::success($data);
    }

    // ------------------------------------------------------------------
    // Wisata — PRD 6.11
    // ------------------------------------------------------------------

    public function wisata(): JsonResponse
    {
        $villageId = $this->village->id();

        $data = Cache::remember("wisata:{$villageId}", self::TTL, function () use ($villageId) {
            $daftar = TourismSpot::query()
                ->where('village_id', $villageId)
                ->tampil()
                ->with('photos')
                ->get()
                ->map(fn ($w) => [
                    'id' => $w->id,
                    'nama' => $w->nama,
                    'slug' => $w->slug,
                    'deskripsi' => $w->deskripsi,
                    'alamat' => $w->alamat,
                    'harga_tiket' => $w->harga_tiket,
                    'foto_utama' => $w->photos->first()
                        ? asset('storage/'.$w->photos->first()->path) : null,
                    'jumlah_foto' => $w->photos->count(),
                ]);

            return $daftar->isEmpty() ? null : $daftar;
        });

        return ApiResponse::success($data);
    }

    public function wisataDetail(string $slug): JsonResponse
    {
        $wisata = TourismSpot::query()
            ->where('village_id', $this->village->id())
            ->where('slug', $slug)
            ->where('status_tampil', true)
            ->with('photos')
            ->firstOrFail();

        return ApiResponse::success([
            'id' => $wisata->id,
            'nama' => $wisata->nama,
            'slug' => $wisata->slug,
            'deskripsi' => $wisata->deskripsi,
            'alamat' => $wisata->alamat,
            'koordinat' => $wisata->latitude === null ? null : [
                'latitude' => $wisata->latitude,
                'longitude' => $wisata->longitude,
            ],
            'jam_operasional' => $wisata->jam_operasional ?? [],
            'harga_tiket' => $wisata->harga_tiket,
            'kontak_pengelola' => $wisata->kontak_pengelola,
            'fasilitas' => $wisata->fasilitas ?? [],
            'foto' => $wisata->photos->map(fn ($f) => [
                'url' => asset('storage/'.$f->path),
                'caption' => $f->caption,
                // Cadangan alt text agar gambar tetap bermakna bagi pembaca
                // layar meski admin belum mengisinya (PRD 12.4).
                'alt_text' => $f->alt_text ?: ($f->caption ?: $wisata->nama),
            ]),
        ]);
    }

    // ------------------------------------------------------------------
    // Katalog UMKM — PRD 6.12
    // ------------------------------------------------------------------

    public function produk(Request $request): JsonResponse
    {
        $request->validate([
            'kategori' => ['nullable', 'string', 'max:100'],
            'cari' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $produk = Product::query()
            ->where('village_id', $this->village->id())
            ->tampil()
            ->with('photos')
            ->when(
                $request->string('kategori')->toString(),
                fn ($q, $k) => $q->where('kategori', $k)
            )
            ->when(
                $request->string('cari')->toString(),
                fn ($q, $cari) => $q->where(
                    fn ($sub) => $sub->where('nama_produk', 'like', "%{$cari}%")
                        ->orWhere('nama_penjual', 'like', "%{$cari}%")
                )
            )
            // Produk tersedia didahulukan; yang habis tetap tampil sebagai
            // informasi, bukan disembunyikan.
            ->orderByDesc('tersedia')
            ->orderBy('nama_produk')
            ->paginate($request->integer('per_page', 12));

        $produk->setCollection($produk->getCollection()->map(fn ($p) => $this->bentukProduk($p)));

        return ApiResponse::paginated($produk);
    }

    public function produkDetail(string $slug): JsonResponse
    {
        $produk = Product::query()
            ->where('village_id', $this->village->id())
            ->where('slug', $slug)
            ->tampil()
            ->with('photos')
            ->firstOrFail();

        return ApiResponse::success([
            ...$this->bentukProduk($produk),
            'deskripsi' => $produk->deskripsi,
            'alamat_penjual' => $produk->alamat_penjual,
            'foto' => $produk->photos->map(fn ($f) => [
                'url' => asset('storage/'.$f->path),
                'alt_text' => $f->alt_text ?: $produk->nama_produk,
            ]),
        ]);
    }

    public function kategoriProduk(): JsonResponse
    {
        $villageId = $this->village->id();

        $data = Cache::remember(
            "produk:kategori:{$villageId}",
            self::TTL,
            fn () => Product::where('village_id', $villageId)
                ->where('status_tampil', true)
                ->whereNotNull('kategori')
                ->selectRaw('kategori, COUNT(*) AS jumlah')
                ->groupBy('kategori')
                ->orderBy('kategori')
                ->get()
                ->map(fn ($k) => ['kategori' => $k->kategori, 'jumlah' => (int) $k->jumlah])
        );

        return ApiResponse::success($data);
    }

    /** @return array<string, mixed> */
    private function bentukProduk(Product $produk): array
    {
        return [
            'id' => $produk->id,
            'nama_produk' => $produk->nama_produk,
            'slug' => $produk->slug,
            'kategori' => $produk->kategori,
            'harga' => $produk->harga === null ? null : (float) $produk->harga,
            'satuan' => $produk->satuan,
            'tersedia' => $produk->tersedia,
            'foto_utama' => $produk->photos->first()
                ? asset('storage/'.$produk->photos->first()->path) : null,
            'penjual' => [
                'nama' => $produk->nama_penjual,
                'whatsapp' => $produk->kontak_wa,
                // Nomor ternormalkan untuk tautan wa.me. Ini sekadar tautan
                // hubungi penjual — bukan alur checkout (DEVIASI A4).
                'whatsapp_link' => $produk->whatsappInternasional()
                    ? 'https://wa.me/'.$produk->whatsappInternasional()
                    : null,
            ],
        ];
    }
}
