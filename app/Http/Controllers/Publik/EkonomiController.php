<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Potential;
use App\Services\DataEkonomi;
use App\Support\Paginasi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Potensi Desa, Wisata, dan Katalog UMKM — PRD 6.11 & 6.12.
 *
 * Seluruh modul ini tinggal di bawah satu alamat, /potensi, termasuk halaman
 * detailnya. Lihat catatan panjang di `routes/web.php` untuk alasannya.
 */
class EkonomiController extends Controller
{
    /**
     * Kategori potensi yang isinya diambil dari modul lain, bukan dari kartu
     * potensi biasa. Dipakai baik oleh daftar maupun halaman detail, supaya
     * keduanya tidak bisa berbeda pendapat tentang slug mana milik siapa.
     */
    private const KATEGORI_WISATA = 'Pariwisata';

    private const KATEGORI_EKONOMI = 'Ekonomi';

    public function __construct(private readonly DataEkonomi $ekonomi) {}

    /**
     * Potensi Desa — satu-satunya pintu masuk ke seluruh modul potensi.
     *
     * Dua kategori tidak diisi kartu potensi melainkan modul yang sudah punya
     * datanya sendiri: Pariwisata memakai daftar destinasi dan Ekonomi memakai
     * katalog UMKM. Sejak menu bercabang di bilah navigasi dihapus, barisan
     * chip di halaman inilah jalan menuju keduanya.
     */
    public function potensi(Request $request): Response
    {
        $this->validasiJelajah($request);

        $kategori = $request->string('kategori')->toString() ?: null;
        $cari = $request->string('cari')->toString() ?: null;
        $jenis = $request->string('jenis')->toString() ?: null;

        return Inertia::render('Publik/Ekonomi/Potensi', [
            // Seluruh kategori, bukan hanya yang terisi: chip yang hilang
            // berarti isinya tidak punya jalan sama sekali untuk dijangkau.
            'kategori' => Potential::KATEGORI,
            'filter' => ['kategori' => $kategori, 'jenis' => $jenis, 'cari' => $cari],
            ...match ($kategori) {
                self::KATEGORI_WISATA => ['wisata' => $this->ekonomi->wisata()],
                self::KATEGORI_EKONOMI => [
                    'produk' => $this->katalog($request, $cari, $jenis),
                    'jenisProduk' => $this->ekonomi->kategoriProduk(),
                ],
                default => ['data' => $this->ekonomi->potensi($kategori)],
            },
        ]);
    }

    /**
     * Detail satu isi potensi, apa pun jenisnya.
     *
     * `?kategori` yang terbawa dari daftarnya menentukan sumber datanya —
     * destinasi wisata, produk UMKM, atau potensi biasa. Ketiga slug itu hidup
     * di tabel yang berbeda, jadi tidak ada risiko satu slug tertukar sumber.
     */
    public function potensiDetail(Request $request, string $slug): Response
    {
        $this->validasiJelajah($request);

        $kategori = $request->string('kategori')->toString() ?: null;
        $kembali = $this->tautanKembali($request);

        // Isi utama selalu diambil lebih dulu daripada isi sidebar-nya, supaya
        // slug yang tidak ada berakhir 404 sebelum kueri sidebar dijalankan.
        if ($kategori === self::KATEGORI_WISATA) {
            return Inertia::render('Publik/Ekonomi/WisataDetail', [
                'wisata' => $this->ekonomi->wisataDetail($slug),
                'wisata_lainnya' => $this->ekonomi->wisataLainnya($slug),
                'kembali' => $kembali,
            ]);
        }

        if ($kategori === self::KATEGORI_EKONOMI) {
            $produk = $this->ekonomi->produkDetail($slug);

            return Inertia::render('Publik/Ekonomi/ProdukDetail', [
                'produk' => $produk,
                'produk_lainnya' => $this->ekonomi->produkLainnya($slug, $produk['kategori']),
                'kembali' => $kembali,
            ]);
        }

        $potensi = $this->ekonomi->potensiDetail($slug);

        return Inertia::render('Publik/Ekonomi/PotensiDetail', [
            'potensi' => $potensi,
            'potensi_lainnya' => $this->ekonomi->potensiLainnya($slug, $potensi['kategori']),
            'kembali' => $kembali,
        ]);
    }

    /**
     * Alamat daftar yang tadi ditinggalkan pengunjung.
     *
     * Query string halaman detail memang sengaja dibuat sama persis dengan
     * query string daftarnya, sehingga memasangnya kembali di depan /potensi
     * memulangkan pengunjung ke kategori, kata kunci, dan nomor halaman yang
     * sama — bukan ke pangkal daftar.
     */
    private function tautanKembali(Request $request): string
    {
        $query = $request->getQueryString();

        return '/potensi'.($query ? '?'.$query : '');
    }

    /**
     * Aturan yang sama dipakai daftar maupun detail: keduanya membawa query
     * string yang identik, jadi keduanya harus menolak isi yang sama pula.
     */
    private function validasiJelajah(Request $request): void
    {
        $request->validate([
            'kategori' => ['nullable', 'string', 'max:100'],
            // Kategori produk pada tab Ekonomi. Sengaja bukan `kategori`:
            // parameter itu sudah dipakai kategori potensi, dan memakai satu
            // nama untuk dua arti membuat "Makanan" terbaca sebagai kategori
            // potensi begitu pengunjung membuka salah satu produknya.
            'jenis' => ['nullable', 'string', 'max:100'],
            'cari' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);
    }

    /**
     * Satu halaman katalog UMKM.
     *
     * @return array{items: array<int, mixed>, meta: array<string, int>}
     */
    private function katalog(Request $request, ?string $cari, ?string $jenis = null): array
    {
        $produk = $this->ekonomi
            ->produk($jenis, $cari, $request->integer('per_page', 12))
            // Tanpa ini, menekan "halaman 2" akan membuang filter kategori
            // maupun kata kunci pencarian yang sedang aktif.
            ->withQueryString();

        return Paginasi::bentuk($produk);
    }
}
