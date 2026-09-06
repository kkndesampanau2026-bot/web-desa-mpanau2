<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VillageBanner;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Banner hero beranda — PRD 6.17.
 *
 * Dipisahkan dari layar Pengaturan Umum, bukan ditumpangkan padanya. Formulir
 * Pengaturan menyimpan seluruh isinya sekaligus; mengunggah dan menghapus
 * gambar tidak cocok dengan pola itu — kegagalan jaringan pada satu gambar
 * akan membatalkan penyimpanan alamat kantor dan jam kerja yang sudah benar.
 * Alasan yang sama sudah dicatat pada `Components/Admin/PengelolaFoto`.
 *
 * Datanya datang sebagai prop Inertia, mengikuti pola modul baru (Fase 4),
 * bukan XHR ke `/api/v1`.
 */
class BannerController extends Controller
{
    /**
     * Batas jumlah banner.
     *
     * Bukan angka yang dikarang: setiap gambar hero berukuran hingga 1920px
     * dan ikut diunduh pengunjung saat carousel berputar. Delapan sudah jauh
     * melampaui kebutuhan sebuah beranda desa, sementara dua puluh akan
     * membuat halaman depan berat justru bagi warga berponsel kelas bawah —
     * yang paling dituju situs ini (PRD 12.5).
     */
    public const MAKS_BANNER = 8;

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly CurrentVillage $village,
        private readonly MediaService $media,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Banner', [
            'daftar_banner' => $this->daftar(),
            'maks_banner' => self::MAKS_BANNER,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $villageId = $this->village->id();
        $terpakai = VillageBanner::where('village_id', $villageId)->count();
        $sisa = max(0, self::MAKS_BANNER - $terpakai);

        $request->validate([
            'gambar' => ['required', 'array', 'min:1', "max:{$sisa}"],
            'gambar.*' => MediaService::aturanGambar(wajib: true),
            'judul' => ['nullable', 'array'],
            'judul.*' => ['nullable', 'string', 'max:255'],
        ], [
            ...MediaService::pesanValidasi('gambar'),
            /*
             * WAJIB berada SESUDAH spread di atas.
             *
             * `pesanValidasi()` juga mendefinisikan `gambar.max`, tetapi
             * kalimatnya berbicara tentang ukuran berkas — sedangkan di sini
             * `max` membatasi JUMLAH gambar. Ditaruh sebelum spread, pesan
             * kuota ini akan tertimpa dan operator diberi tahu ukuran
             * berkasnya terlalu besar padahal yang penuh adalah kuotanya.
             */
            'gambar.max' => $sisa === 0
                ? 'Jumlah banner sudah mencapai batas '.self::MAKS_BANNER.'. Hapus salah satu lebih dulu.'
                : "Tersisa ruang untuk {$sisa} banner lagi.",
        ]);

        $judul = $request->input('judul', []);
        // Nomor urut dilanjutkan dari yang terbesar, bukan dari hitungan baris:
        // penghapusan di tengah membuat keduanya berbeda, dan memakai hitungan
        // akan menabrakkan urutan banner baru dengan yang sudah ada.
        $urutan = (int) VillageBanner::where('village_id', $villageId)->max('urutan_tampil');

        $dibuat = [];

        DB::transaction(function () use ($request, $villageId, $judul, &$urutan, &$dibuat) {
            foreach ($request->file('gambar') as $i => $berkas) {
                $path = $this->media->simpanGambar(
                    $berkas,
                    'banner',
                    null,
                    MediaService::LEBAR_MAKS_BANNER,
                );

                $dibuat[] = VillageBanner::create([
                    'village_id' => $villageId,
                    'path' => $path,
                    'judul' => $judul[$i] ?? null,
                    'urutan_tampil' => ++$urutan,
                ]);
            }
        });

        $this->bersihkanCache();

        $this->logger->log(
            'banner_ditambah',
            null,
            count($dibuat).' banner beranda ditambahkan',
            dataSesudah: ['path' => array_map(fn ($b) => $b->path, $dibuat)],
        );

        return back()->with('sukses', count($dibuat).' banner berhasil diunggah.');
    }

    public function update(Request $request, VillageBanner $banner): RedirectResponse
    {
        $this->pastikanMilikDesaIni($banner);

        $data = $request->validate([
            'judul' => ['nullable', 'string', 'max:255'],
        ]);

        $banner->update(['judul' => $data['judul'] ?? null]);
        $this->bersihkanCache();

        return back()->with('sukses', 'Keterangan banner diperbarui.');
    }

    /**
     * Menggeser satu banner satu langkah ke atas atau ke bawah.
     *
     * Dilakukan dengan MENUKAR nilai urutan dua baris bertetangga, bukan
     * menulis ulang seluruh daftar dari sisi klien. Daftar yang dikirim klien
     * dapat kedaluwarsa — operator lain menghapus satu baris di sela-selanya —
     * dan menuliskannya utuh akan menghidupkan kembali baris yang sudah hilang
     * atau menimpa urutan yang baru saja diubah.
     */
    public function geser(Request $request, VillageBanner $banner): RedirectResponse
    {
        $this->pastikanMilikDesaIni($banner);

        $data = $request->validate([
            'arah' => ['required', 'in:naik,turun'],
        ]);

        $naik = $data['arah'] === 'naik';

        $tetangga = VillageBanner::where('village_id', $banner->village_id)
            ->where('id', '!=', $banner->id)
            ->when(
                $naik,
                fn ($q) => $q->where('urutan_tampil', '<=', $banner->urutan_tampil)
                    ->orderByDesc('urutan_tampil')->orderByDesc('id'),
                fn ($q) => $q->where('urutan_tampil', '>=', $banner->urutan_tampil)
                    ->orderBy('urutan_tampil')->orderBy('id'),
            )
            ->first();

        // Sudah di ujung daftar: bukan kesalahan, hanya tidak ada yang berubah.
        if ($tetangga === null) {
            return back();
        }

        DB::transaction(function () use ($banner, $tetangga) {
            $urutanBanner = $banner->urutan_tampil;
            $urutanTetangga = $tetangga->urutan_tampil;

            // Nilai yang sama (mis. dua baris warisan bernomor 0) tidak akan
            // berpindah bila sekadar ditukar — beri jarak tegas.
            if ($urutanBanner === $urutanTetangga) {
                $urutanBanner = $urutanTetangga + ($banner->id > $tetangga->id ? 1 : -1);
            }

            $banner->update(['urutan_tampil' => $urutanTetangga]);
            $tetangga->update(['urutan_tampil' => $urutanBanner]);
        });

        $this->bersihkanCache();

        return back();
    }

    public function destroy(VillageBanner $banner): RedirectResponse
    {
        $this->pastikanMilikDesaIni($banner);

        $path = $banner->path;

        DB::transaction(function () use ($banner) {
            $banner->delete();
        });

        // Berkasnya dihapus SETELAH barisnya hilang: urutan sebaliknya
        // menyisakan baris yang menunjuk gambar tidak ada bila penghapusan
        // basis data gagal.
        $this->media->hapus($path);
        $this->bersihkanCache();

        $this->logger->log('banner_dihapus', null, 'Banner beranda dihapus', dataSebelum: ['path' => $path]);

        return back()->with('sukses', 'Banner berhasil dihapus.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function daftar(): array
    {
        return VillageBanner::where('village_id', $this->village->id())
            ->orderBy('urutan_tampil')
            ->orderBy('id')
            ->get()
            ->map(fn (VillageBanner $b) => [
                'id' => $b->id,
                'url' => asset('storage/'.$b->path),
                'judul' => $b->judul,
            ])
            ->values()
            ->all();
    }

    private function pastikanMilikDesaIni(VillageBanner $banner): void
    {
        abort_unless($banner->village_id === $this->village->id(), 404);
    }

    /**
     * Prop bersama `pengaturan` di-cache satu jam oleh `PengaturanSitus`.
     * Tanpa pembersihan ini, banner yang baru diunggah tidak muncul di beranda
     * sampai cache-nya kedaluwarsa sendiri — dan operator menyimpulkan
     * unggahannya gagal.
     */
    private function bersihkanCache(): void
    {
        Cache::forget('settings:'.$this->village->id());
    }
}
