<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\LetterRequest;
use App\Models\News;
use App\Models\PointOfInterest;
use App\Models\Potential;
use App\Models\Product;
use App\Models\Resident;
use App\Models\User;
use App\Services\CurrentVillage;
use App\Services\VisitorTracker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda dashboard CMS — PRD 5.20.
 *
 * Menjawab pertanyaan yang diajukan operator setiap kali masuk: *apa yang
 * perlu saya kerjakan, dan bagaimana keadaan situs desa?* Sebelumnya halaman
 * ini hanya menampilkan daftar permission akun — berguna sekali saat
 * pemasangan, lalu tidak pernah lagi.
 *
 * Tiga aturan yang mengikat isinya:
 *
 * 1. **Disaring per izin, bukan disembunyikan di antarmuka.** Angka pun dapat
 *    membocorkan hal di luar kewenangan seseorang — "3 aduan baru" memberi
 *    tahu Operator PPID bahwa ada tiga warga sedang mengadu (PRD 12.2).
 *    Penyaringannya karena itu di sini, bukan di komponen React.
 * 2. **Tidak mengulang lonceng notifikasi.** Antrean pekerjaan sudah tiba
 *    lewat prop bersama `notifikasi` (lihat `NotifikasiAdmin`) yang menyertai
 *    setiap halaman admin; dashboard memakainya kembali alih-alih menjalankan
 *    kueri yang sama untuk kedua kalinya.
 * 3. **Angka "bulan ini" dihitung dari waktu peristiwanya**, bukan dari
 *    `updated_at`. Menyunting tanggapan sebuah aduan lama tidak boleh
 *    membuatnya terhitung sebagai aduan yang selesai bulan ini.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly VisitorTracker $kunjungan,
    ) {}

    public function __invoke(Request $request): Response
    {
        $pengguna = $request->user();

        return Inertia::render('Admin/Dashboard', [
            // Kunjungan tidak menyangkut data pribadi siapa pun, sehingga
            // terbuka bagi setiap operator yang dapat membuka dashboard.
            'kunjungan' => $this->kunjungan->ringkasan($this->village->id()),
            'tren_kunjungan' => $this->kunjungan->tren(30, $this->village->id()),
            'ringkasan' => $this->ringkasan($pengguna),
            'berita_terbaru' => $pengguna?->can('manage-news')
                ? $this->beritaTerbaru()
                : null,
        ]);
    }

    /**
     * Kartu angka keadaan isi situs.
     *
     * Daftarnya berbeda-beda per peran, dan itu memang tujuannya: setiap baris
     * hanya ikut bila operatornya berwenang atas modul yang bersangkutan.
     *
     * @return list<array<string, mixed>>
     */
    private function ringkasan(?User $pengguna): array
    {
        if ($pengguna === null) {
            return [];
        }

        $desa = $this->village->id();
        $awalBulan = now()->startOfMonth();
        $kartu = [];

        if ($pengguna->can('manage-population-data')) {
            $kartu[] = [
                'kunci' => 'penduduk',
                'label' => 'Warga Terdata',
                'nilai' => Resident::where('village_id', $desa)->count(),
                'satuan' => 'jiwa',
                'ke' => '/admin/penduduk',
            ];
        }

        if ($pengguna->can('manage-news')) {
            $draf = News::where('village_id', $desa)->where('status', 'draft')->count();

            $kartu[] = [
                'kunci' => 'berita',
                'label' => 'Berita Tayang',
                'nilai' => News::where('village_id', $desa)->tayang()->count(),
                'satuan' => 'artikel',
                // Draf yang menumpuk adalah pekerjaan yang terlupakan, bukan
                // pencapaian — karena itu ikut disebut di samping angkanya.
                'keterangan' => $draf > 0 ? "{$draf} masih draf" : null,
                'ke' => '/admin/berita',
            ];
        }

        /*
         * Potensi dan produk UMKM tampil di satu layar (/admin/ekonomi) namun
         * dijaga dua izin terpisah, jadi keduanya pun dihitung terpisah.
         * Menjumlahkannya menjadi satu angka berarti operator yang hanya
         * memegang salah satunya tetap membaca jumlah yang lain.
         */
        if ($pengguna->can('manage-potential')) {
            $kartu[] = [
                'kunci' => 'potensi',
                'label' => 'Potensi Desa',
                'nilai' => Potential::where('village_id', $desa)
                    ->where('status_tampil', true)->count(),
                'satuan' => 'tayang',
                'ke' => '/admin/ekonomi',
            ];
        }

        if ($pengguna->can('manage-product')) {
            $kartu[] = [
                'kunci' => 'produk',
                'label' => 'Produk UMKM',
                'nilai' => Product::where('village_id', $desa)
                    ->where('status_tampil', true)->count(),
                'satuan' => 'tayang',
                'ke' => '/admin/ekonomi',
            ];
        }

        if ($pengguna->can('manage-poi')) {
            $kartu[] = [
                'kunci' => 'peta',
                'label' => 'Titik Peta',
                'nilai' => PointOfInterest::where('village_id', $desa)
                    ->where('status_tampil', true)->count(),
                'satuan' => 'titik',
                'ke' => '/admin/peta',
            ];
        }

        if ($pengguna->can('manage-letter-request')) {
            $kartu[] = [
                'kunci' => 'surat',
                'label' => 'Surat Terbit',
                // Waktu terbitnya adalah saat Kepala Dusun menyetujui, bukan
                // saat barisnya terakhir tersentuh.
                'nilai' => LetterRequest::where('village_id', $desa)
                    ->where('status', LetterRequest::DISETUJUI)
                    ->where('approved_kadus_at', '>=', $awalBulan)
                    ->count(),
                'satuan' => 'bulan ini',
                'ke' => '/admin/surat',
            ];
        }

        if ($pengguna->can('respond-complaint')) {
            $kartu[] = [
                'kunci' => 'pengaduan',
                'label' => 'Aduan Selesai',
                'nilai' => Complaint::where('village_id', $desa)
                    ->where('status', 'selesai')
                    ->where('tanggal_tanggapan', '>=', $awalBulan)
                    ->count(),
                'satuan' => 'bulan ini',
                'ke' => '/admin/pengaduan',
            ];
        }

        return $kartu;
    }

    /**
     * Lima berita terakhir disunting.
     *
     * Bukan hiasan: inilah yang memberi tahu operator apakah situs desanya
     * masih hidup. Draf ikut tampil — justru yang belum terbit yang paling
     * mudah terlupakan.
     *
     * @return list<array<string, mixed>>
     */
    private function beritaTerbaru(): array
    {
        return News::where('village_id', $this->village->id())
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'judul', 'status', 'tanggal_publish', 'jumlah_dilihat'])
            ->map(fn (News $berita) => [
                'id' => $berita->id,
                'judul' => $berita->judul,
                'status' => $berita->status,
                'tanggal_publish' => $berita->tanggal_publish?->toIso8601String(),
                'jumlah_dilihat' => $berita->jumlah_dilihat,
            ])
            ->all();
    }
}
