<?php

namespace App\Services;

use App\Models\Potential;
use App\Models\Product;
use App\Models\TourismSpot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Potensi Desa, Wisata, dan Katalog UMKM — PRD 6.11 & 6.12.
 *
 * Ketiganya disatukan karena berbagi sifat yang sama: konten promosi desa yang
 * sepenuhnya publik, tanpa data pribadi warga dan tanpa aturan kerahasiaan
 * khusus.
 */
class DataEkonomi
{
    private const TTL = 1800;

    public function __construct(private readonly CurrentVillage $village) {}

    // ------------------------------------------------------------------
    // Potensi Desa — PRD 6.11
    // ------------------------------------------------------------------

    /**
     * Daftar potensi untuk halaman /potensi.
     *
     * Seluruh kategori ikut, termasuk Pariwisata dan Ekonomi. Keduanya sempat
     * dikeluarkan karena diselipkan ke /wisata dan /belanja — akibatnya dua
     * kategori hilang dari filter dan pengunjung yang menyaring "Ekonomi" di
     * sini selalu menemui daftar kosong.
     *
     * @return array<string, mixed>|null
     */
    public function potensi(?string $kategori = null): ?array
    {
        $daftar = $this->potensiTampil();

        if ($daftar->isEmpty()) {
            return null;   // empty-state (PRD 3.2)
        }

        return [
            'items' => ($kategori ? $daftar->where('kategori', $kategori) : $daftar)
                ->values(),
            // Kategori yang benar-benar terisi saja — menampilkan filter
            // yang pasti kosong hanya membuat pengunjung menemui jalan buntu.
            'kategori_tersedia' => $daftar->pluck('kategori')->unique()->sort()->values(),
        ];
    }

    /**
     * Satu potensi beserta seluruh isinya — halaman detail publik.
     *
     * @return array<string, mixed>
     */
    public function potensiDetail(string $slug): array
    {
        $potensi = Potential::query()
            ->where('village_id', $this->village->id())
            ->where('slug', $slug)
            ->where('status_tampil', true)
            ->firstOrFail();

        return $this->bentukPotensi($potensi);
    }

    /**
     * Seluruh potensi yang ditampilkan, sudah dibentuk siap pakai.
     *
     * Disimpan sebagai satu entri cache lalu disaring di PHP. Sebelumnya tiap
     * kombinasi filter punya kuncinya sendiri, sehingga satu penyuntingan di
     * CMS harus membatalkan sebanyak mungkin kunci sebagaimana kategori yang
     * ada — dan satu kunci yang terlewat berarti data basi di situs publik.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function potensiTampil(): Collection
    {
        $villageId = $this->village->id();

        return Cache::remember(
            "potensi:{$villageId}",
            self::TTL,
            fn () => Potential::query()
                ->where('village_id', $villageId)
                ->tampil()
                ->get()
                ->map(fn ($p) => $this->bentukPotensi($p))
                ->values()
        );
    }

    /** @return array<string, mixed> */
    private function bentukPotensi(Potential $potensi): array
    {
        return [
            'id' => $potensi->id,
            'kategori' => $potensi->kategori,
            'judul' => $potensi->judul,
            'slug' => $potensi->slug,
            'deskripsi' => $potensi->deskripsi,
            'foto' => $potensi->foto ? asset('storage/'.$potensi->foto) : null,
            'koordinat' => $potensi->latitude === null ? null : [
                'latitude' => $potensi->latitude,
                'longitude' => $potensi->longitude,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Wisata — PRD 6.11
    // ------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function wisata(): ?array
    {
        $villageId = $this->village->id();

        return Cache::remember("wisata:{$villageId}", self::TTL, function () use ($villageId) {
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

            return $daftar->isEmpty() ? null : $daftar->all();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function wisataDetail(string $slug): array
    {
        $wisata = TourismSpot::query()
            ->where('village_id', $this->village->id())
            ->where('slug', $slug)
            ->where('status_tampil', true)
            ->with('photos')
            ->firstOrFail();

        return [
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
        ];
    }

    // ------------------------------------------------------------------
    // Katalog UMKM — PRD 6.12
    // ------------------------------------------------------------------

    public function produk(?string $kategori, ?string $cari, int $perHalaman = 12): LengthAwarePaginator
    {
        $produk = Product::query()
            ->where('village_id', $this->village->id())
            ->tampil()
            ->with('photos')
            ->when($kategori, fn ($q, $k) => $q->where('kategori', $k))
            ->when(
                $cari,
                fn ($q, $cari) => $q->where(
                    fn ($sub) => $sub->where('nama_produk', 'like', "%{$cari}%")
                        ->orWhere('nama_penjual', 'like', "%{$cari}%")
                )
            )
            // Produk tersedia didahulukan; yang habis tetap tampil sebagai
            // informasi, bukan disembunyikan.
            ->orderByDesc('tersedia')
            ->orderBy('nama_produk')
            ->paginate($perHalaman);

        $produk->setCollection($produk->getCollection()->map(fn ($p) => $this->bentukProduk($p)));

        return $produk;
    }

    /**
     * @return array<string, mixed>
     */
    public function produkDetail(string $slug): array
    {
        $produk = Product::query()
            ->where('village_id', $this->village->id())
            ->where('slug', $slug)
            ->tampil()
            ->with('photos')
            ->firstOrFail();

        return [
            ...$this->bentukProduk($produk),
            'deskripsi' => $produk->deskripsi,
            'alamat_penjual' => $produk->alamat_penjual,
            'foto' => $produk->photos->map(fn ($f) => [
                'url' => asset('storage/'.$f->path),
                'alt_text' => $f->alt_text ?: $produk->nama_produk,
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function kategoriProduk(): array
    {
        $villageId = $this->village->id();

        return Cache::remember(
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
                ->all()
        );
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
