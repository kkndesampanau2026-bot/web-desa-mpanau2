<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Potential;
use App\Models\Product;
use App\Models\TourismSpot;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * CMS Potensi Desa, Wisata, dan Produk UMKM — PRD 5.12–5.14.
 *
 * Ketiganya modul konten non-sensitif, sehingga cukup dijaga permission
 * masing-masing tanpa lapisan tambahan seperti pada modul kependudukan.
 */
class EkonomiController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
        private readonly MediaService $media,
    ) {}

    // ------------------------------------------------------------------
    // Potensi Desa
    // ------------------------------------------------------------------

    public function potensiIndex(): JsonResponse
    {
        $data = Potential::where('village_id', $this->village->id())
            ->orderBy('urutan_tampil')
            ->orderBy('judul')
            ->get();

        return ApiResponse::success($data);
    }

    public function potensiSimpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kategori' => ['required', Rule::in(Potential::KATEGORI)],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status_tampil' => ['nullable', 'boolean'],
            'urutan_tampil' => ['nullable', 'integer', 'min:0'],
            'foto' => MediaService::aturanGambar(),
        ], MediaService::pesanValidasi('foto'));

        if ($request->hasFile('foto')) {
            $data['foto'] = $this->media->simpanGambar($request->file('foto'), 'potensi');
        }

        $potensi = Potential::create([
            ...$data,
            'village_id' => $this->village->id(),
            'slug' => $this->slugUnik(Potential::class, $data['judul']),
        ]);

        $this->bersihkanCachePotensi();
        $this->logger->log('created', $potensi, "Menambah potensi desa: {$potensi->judul}");

        return ApiResponse::success($potensi, 'Potensi desa berhasil ditambahkan.', 201);
    }

    public function potensiUbah(Request $request, Potential $potential): JsonResponse
    {
        $this->pastikanMilikDesaIni($potential->village_id);

        $data = $request->validate([
            'kategori' => ['required', Rule::in(Potential::KATEGORI)],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status_tampil' => ['nullable', 'boolean'],
            'urutan_tampil' => ['nullable', 'integer', 'min:0'],
            'foto' => MediaService::aturanGambar(),
        ], MediaService::pesanValidasi('foto'));

        if ($request->hasFile('foto')) {
            $data['foto'] = $this->media->simpanGambar(
                $request->file('foto'), 'potensi', $potential->foto
            );
        } else {
            unset($data['foto']);
        }

        $sebelum = $potential->getOriginal();

        // Slug hanya dibuat ulang bila judul berubah, supaya tautan yang sudah
        // tersebar tidak putus.
        if ($data['judul'] !== $potential->judul) {
            $data['slug'] = $this->slugUnik(Potential::class, $data['judul'], $potential->id);
        }

        $potential->update($data);
        $this->bersihkanCachePotensi();

        $this->logger->log(
            'updated', $potential, "Memperbarui potensi desa: {$potential->judul}",
            $sebelum, $potential->getAttributes()
        );

        return ApiResponse::success($potential, 'Potensi desa berhasil diperbarui.');
    }

    public function potensiHapus(Potential $potential): JsonResponse
    {
        $this->pastikanMilikDesaIni($potential->village_id);

        $judul = $potential->judul;

        $this->media->hapus($potential->foto);
        $potential->delete();
        $this->bersihkanCachePotensi();

        $this->logger->log('deleted', $potential, "Menghapus potensi desa: {$judul}");

        return ApiResponse::success(message: 'Potensi desa berhasil dihapus.');
    }

    // ------------------------------------------------------------------
    // Wisata
    // ------------------------------------------------------------------

    public function wisataIndex(): JsonResponse
    {
        // Foto ikut dimuat, bukan sekadar dihitung: halaman CMS perlu
        // menampilkan dan menghapusnya satu per satu, yang mustahil dilakukan
        // hanya dengan angka jumlah.
        $data = TourismSpot::where('village_id', $this->village->id())
            ->with('photos')
            ->withCount('photos')
            ->orderBy('nama')
            ->get();

        return ApiResponse::success($data);
    }

    public function wisataSimpan(Request $request): JsonResponse
    {
        $data = $this->validasiWisata($request);

        $wisata = TourismSpot::create([
            ...$data,
            'village_id' => $this->village->id(),
            'slug' => $this->slugUnik(TourismSpot::class, $data['nama']),
        ]);

        $this->bersihkanCacheWisata();
        $this->logger->log('created', $wisata, "Menambah destinasi wisata: {$wisata->nama}");

        return ApiResponse::success($wisata, 'Destinasi wisata berhasil ditambahkan.', 201);
    }

    public function wisataUbah(Request $request, TourismSpot $tourismSpot): JsonResponse
    {
        $this->pastikanMilikDesaIni($tourismSpot->village_id);

        $data = $this->validasiWisata($request);
        $sebelum = $tourismSpot->getOriginal();

        if ($data['nama'] !== $tourismSpot->nama) {
            $data['slug'] = $this->slugUnik(TourismSpot::class, $data['nama'], $tourismSpot->id);
        }

        $tourismSpot->update($data);
        $this->bersihkanCacheWisata();

        $this->logger->log(
            'updated', $tourismSpot, "Memperbarui destinasi wisata: {$tourismSpot->nama}",
            $sebelum, $tourismSpot->getAttributes()
        );

        return ApiResponse::success($tourismSpot, 'Destinasi wisata berhasil diperbarui.');
    }

    public function wisataHapus(TourismSpot $tourismSpot): JsonResponse
    {
        $this->pastikanMilikDesaIni($tourismSpot->village_id);

        $nama = $tourismSpot->nama;

        // Berkas dikumpulkan selagi barisnya masih ada: foto anak ikut lenyap
        // lewat cascade basis data, sehingga path-nya tak lagi bisa dilacak
        // setelah penghapusan.
        foreach ($tourismSpot->photos as $foto) {
            $this->media->hapus($foto->path);
        }

        $tourismSpot->delete();
        $this->bersihkanCacheWisata();

        $this->logger->log('deleted', $tourismSpot, "Menghapus destinasi wisata: {$nama}");

        return ApiResponse::success(message: 'Destinasi wisata berhasil dihapus.');
    }

    // ------------------------------------------------------------------
    // Produk UMKM
    // ------------------------------------------------------------------

    public function produkIndex(Request $request): JsonResponse
    {
        $produk = Product::query()
            ->where('village_id', $this->village->id())
            ->with('photos')
            ->withCount('photos')
            ->when(
                $request->string('cari')->toString(),
                fn ($q, $cari) => $q->where('nama_produk', 'like', "%{$cari}%")
            )
            ->orderBy('nama_produk')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::paginated($produk);
    }

    public function produkSimpan(Request $request): JsonResponse
    {
        $data = $this->validasiProduk($request);

        $produk = Product::create([
            ...$data,
            'village_id' => $this->village->id(),
            'slug' => $this->slugUnik(Product::class, $data['nama_produk']),
        ]);

        $this->bersihkanCacheProduk();
        $this->logger->log('created', $produk, "Menambah produk UMKM: {$produk->nama_produk}");

        return ApiResponse::success($produk, 'Produk berhasil ditambahkan.', 201);
    }

    public function produkUbah(Request $request, Product $product): JsonResponse
    {
        $this->pastikanMilikDesaIni($product->village_id);

        $data = $this->validasiProduk($request);
        $sebelum = $product->getOriginal();

        if ($data['nama_produk'] !== $product->nama_produk) {
            $data['slug'] = $this->slugUnik(Product::class, $data['nama_produk'], $product->id);
        }

        $product->update($data);
        $this->bersihkanCacheProduk();

        $this->logger->log(
            'updated', $product, "Memperbarui produk UMKM: {$product->nama_produk}",
            $sebelum, $product->getAttributes()
        );

        return ApiResponse::success($product, 'Produk berhasil diperbarui.');
    }

    public function produkHapus(Product $product): JsonResponse
    {
        $this->pastikanMilikDesaIni($product->village_id);

        $nama = $product->nama_produk;
        $product->delete();
        $this->bersihkanCacheProduk();

        $this->logger->log('deleted', $product, "Menghapus produk UMKM: {$nama}");

        return ApiResponse::success(message: 'Produk berhasil dihapus.');
    }

    // ------------------------------------------------------------------
    // Foto wisata & produk
    // ------------------------------------------------------------------

    /** Batas foto per unggahan — menjaga request tetap di bawah post_max_size. */
    private const MAKS_FOTO_SEKALI_UNGGAH = 10;

    public function wisataFotoUnggah(Request $request, TourismSpot $tourismSpot): JsonResponse
    {
        $this->pastikanMilikDesaIni($tourismSpot->village_id);

        $hasil = $this->unggahFoto($request, $tourismSpot->photos(), 'wisata', ['caption', 'alt_text']);
        $this->bersihkanCacheWisata();

        $jumlah = count($hasil);
        $this->logger->log(
            'created', $tourismSpot,
            "Mengunggah {$jumlah} foto destinasi wisata: {$tourismSpot->nama}"
        );

        return ApiResponse::success($hasil, "{$jumlah} foto berhasil diunggah.", 201);
    }

    public function wisataFotoHapus(TourismSpot $tourismSpot, int $photo): JsonResponse
    {
        $this->pastikanMilikDesaIni($tourismSpot->village_id);

        // Dicari lewat relasi, bukan route model binding, supaya foto milik
        // destinasi lain tidak bisa dihapus dengan menebak id.
        $berkas = $tourismSpot->photos()->findOrFail($photo);

        $this->media->hapus($berkas->path);
        $berkas->delete();
        $this->bersihkanCacheWisata();

        $this->logger->log('deleted', $tourismSpot, "Menghapus foto wisata: {$tourismSpot->nama}");

        return ApiResponse::success(message: 'Foto berhasil dihapus.');
    }

    public function produkFotoUnggah(Request $request, Product $product): JsonResponse
    {
        $this->pastikanMilikDesaIni($product->village_id);

        $hasil = $this->unggahFoto($request, $product->photos(), 'produk', ['alt_text']);
        $this->bersihkanCacheProduk();

        $jumlah = count($hasil);
        $this->logger->log(
            'created', $product,
            "Mengunggah {$jumlah} foto produk UMKM: {$product->nama_produk}"
        );

        return ApiResponse::success($hasil, "{$jumlah} foto berhasil diunggah.", 201);
    }

    public function produkFotoHapus(Product $product, int $photo): JsonResponse
    {
        $this->pastikanMilikDesaIni($product->village_id);

        $berkas = $product->photos()->findOrFail($photo);

        $this->media->hapus($berkas->path);
        $berkas->delete();
        $this->bersihkanCacheProduk();

        $this->logger->log('deleted', $product, "Menghapus foto produk: {$product->nama_produk}");

        return ApiResponse::success(message: 'Foto berhasil dihapus.');
    }

    /**
     * Menyimpan satu batch foto ke dalam relasi milik induknya.
     *
     * @param  list<string>  $keterangan  kolom teks opsional yang menyertai tiap foto
     * @return list<Model>
     */
    private function unggahFoto(
        Request $request,
        HasMany $relasi,
        string $folder,
        array $keterangan,
    ): array {
        $aturan = [
            'foto' => ['required', 'array', 'max:'.self::MAKS_FOTO_SEKALI_UNGGAH],
            'foto.*' => MediaService::aturanGambar(wajib: true),
        ];

        foreach ($keterangan as $kolom) {
            $aturan[$kolom] = ['nullable', 'array'];
            $aturan["{$kolom}.*"] = ['nullable', 'string', 'max:255'];
        }

        $request->validate($aturan, [
            ...MediaService::pesanValidasi('foto.*'),
            'foto.required' => 'Pilih setidaknya satu foto untuk diunggah.',
            'foto.max' => 'Maksimal '.self::MAKS_FOTO_SEKALI_UNGGAH.' foto dalam sekali unggah.',
        ]);

        // Urutan melanjutkan foto yang sudah ada, bukan dimulai dari nol, agar
        // batch kedua tidak menumpuk di posisi yang sama dengan batch pertama.
        $urutan = (int) $relasi->max('urutan_tampil');

        return DB::transaction(function () use ($request, $relasi, $folder, $keterangan, &$urutan) {
            $hasil = [];

            foreach ($request->file('foto') as $i => $berkas) {
                $baris = ['path' => $this->media->simpanGambar($berkas, $folder)];

                foreach ($keterangan as $kolom) {
                    $baris[$kolom] = $request->input("{$kolom}.{$i}");
                }

                $baris['urutan_tampil'] = ++$urutan;
                $hasil[] = $relasi->create($baris);
            }

            return $hasil;
        });
    }

    /** @return array<string, mixed> */
    private function validasiWisata(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'jam_operasional' => ['nullable', 'array'],
            // Teks bebas: PRD 6.11 memperbolehkan nilai seperti "Gratis".
            'harga_tiket' => ['nullable', 'string', 'max:100'],
            'kontak_pengelola' => ['nullable', 'string', 'max:255'],
            'fasilitas' => ['nullable', 'array'],
            'fasilitas.*' => ['string', 'max:100'],
            'status_tampil' => ['nullable', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validasiProduk(Request $request): array
    {
        return $request->validate([
            'nama_produk' => ['required', 'string', 'max:255'],
            'kategori' => ['nullable', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'harga' => ['nullable', 'numeric', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'tersedia' => ['nullable', 'boolean'],
            'nama_penjual' => ['required', 'string', 'max:255'],
            // Nomor Indonesia: 08xx, 62xx, atau +62xx; tanda pisah diperbolehkan
            // karena warga lazim menuliskannya dengan spasi atau strip.
            'kontak_wa' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{8,}$/'],
            'alamat_penjual' => ['nullable', 'string', 'max:500'],
            'dusun_id' => [
                'nullable',
                Rule::exists('dusuns', 'id')->where('village_id', $this->village->id()),
            ],
            'status_tampil' => ['nullable', 'boolean'],
        ], [
            'kontak_wa.regex' => 'Nomor WhatsApp tidak dikenali. Gunakan format 08xx atau +62xx.',
        ]);
    }

    /**
     * Slug unik per desa.
     *
     * @param  class-string<Model>  $model
     */
    private function slugUnik(string $model, string $judul, ?int $kecualikanId = null): string
    {
        $dasar = Str::slug($judul) ?: 'item';
        $slug = $dasar;
        $n = 2;

        while (
            $model::withoutGlobalScopes()
                ->where('village_id', $this->village->id())
                ->where('slug', $slug)
                ->when($kecualikanId, fn ($q) => $q->where('id', '!=', $kecualikanId))
                ->exists()
        ) {
            $slug = "{$dasar}-{$n}";
            $n++;
        }

        return $slug;
    }

    private function pastikanMilikDesaIni(?int $villageId): void
    {
        abort_unless($villageId === $this->village->id(), 404);
    }

    private function bersihkanCachePotensi(): void
    {
        // Seluruh potensi desa ini hidup di satu entri cache, sehingga satu
        // panggilan cukup — tidak ada lagi kunci per-kategori yang bisa
        // terlewat dan menyisakan data basi di situs publik.
        Cache::forget("potensi:{$this->village->id()}");
    }

    private function bersihkanCacheWisata(): void
    {
        Cache::forget("wisata:{$this->village->id()}");
    }

    private function bersihkanCacheProduk(): void
    {
        Cache::forget("produk:kategori:{$this->village->id()}");
    }
}
