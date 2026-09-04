<?php

namespace App\Http\Middleware;

use App\Models\Complaint;
use App\Services\CurrentVillage;
use App\Services\PengaturanSitus;
use App\Services\VisitorTracker;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Data yang menyertai SETIAP respons Inertia.
 *
 * Ini pengganti langsung dari pekerjaan yang dulu dilakukan berulang di sisi
 * klien: Admin App memanggil `/auth/me` saat bootstrap untuk memulihkan sesi,
 * dan setiap halaman publik memanggil `/settings` demi mengisi header & footer.
 * Keduanya kini ikut menumpang pada respons halaman, sehingga tidak ada lagi
 * kedipan "memuat sesi…" maupun permintaan HTTP tambahan per navigasi.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $diAreaAdmin = $request->is('admin', 'admin/*');

        return [
            ...parent::share($request),

            'auth' => [
                'user' => fn () => ($user = $request->user()) ? [
                    'id' => $user->id,
                    'nama' => $user->name,
                    'email' => $user->email,
                    'no_telepon' => $user->no_telepon,
                    'village_id' => $user->village_id,
                    'roles' => $user->getRoleNames(),
                    // Dikirim agar antarmuka dapat menyembunyikan menu/aksi
                    // yang tidak diizinkan. Ini murni demi UX — otorisasi
                    // sesungguhnya tetap ditegakkan server pada tiap route.
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ] : null,
            ],

            /*
             * Identitas desa & statistik kunjungan hanya dibagikan pada halaman
             * publik. Dashboard CMS tidak memakai header/footer situs, dan
             * menyertakannya di sana berarti setiap navigasi operator membawa
             * satu blok data mati.
             */
            'pengaturan' => $diAreaAdmin
                ? null
                : fn () => app(PengaturanSitus::class)->untukTataLetak(),

            'statistik_kunjungan' => $diAreaAdmin
                ? null
                : fn () => app(VisitorTracker::class)->ringkasan(app(CurrentVillage::class)->id()),

            /*
             * Kategori pengaduan dibagikan agar tombol mengambang "Aduan Warga"
             * dapat menampilkan formulir lengkap dari halaman publik mana pun,
             * tanpa permintaan tambahan. Konstanta kecil, jadi murah dibawa.
             */
            'kategori_pengaduan' => $diAreaAdmin ? null : Complaint::KATEGORI,

            /*
             * Pengaduan berstatus "baru" — dibagikan ke SELURUH layar dashboard
             * (bukan hanya halaman Pengaduan) agar lonceng notifikasi pada
             * `LayoutAdmin` tetap terisi ke mana pun operator bernavigasi, tanpa
             * permintaan tambahan per halaman. Hanya dihitung untuk operator
             * yang berwenang menanggapi pengaduan.
             */
            'notifikasi_pengaduan' => $diAreaAdmin && $request->user()?->can('respond-complaint')
                ? fn () => $this->notifikasiPengaduan()
                : null,

            /*
             * Pesan sekali-tampil setelah redirect. Menggantikan pola lama di
             * mana komponen React menyimpan sendiri hasil mutasi dari respons
             * axios — kini server yang menyatakannya dan Inertia yang membawa.
             */
            'flash' => [
                'sukses' => fn () => $request->session()->get('sukses'),
                'galat' => fn () => $request->session()->get('galat'),
            ],
        ];
    }

    /**
     * Ringkasan pengaduan belum ditanggapi untuk dropdown lonceng notifikasi.
     *
     * Daftarnya dibatasi 5 teratas — cukup untuk pratinjau, dan menghindari
     * membawa seluruh isi pengaduan (yang bisa panjang) pada SETIAP respons
     * halaman dashboard. Daftar lengkapnya tetap ada di /admin/pengaduan.
     *
     * @return array{jumlah: int, daftar: array<int, array<string, mixed>>}
     */
    private function notifikasiPengaduan(): array
    {
        $baru = Complaint::where('village_id', app(CurrentVillage::class)->id())
            ->where('status', 'baru');

        return [
            'jumlah' => (clone $baru)->count(),
            'daftar' => (clone $baru)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'nomor_tiket', 'nama', 'kategori_pengaduan', 'isi_pengaduan', 'created_at'])
                ->map(fn (Complaint $p) => [
                    'id' => $p->id,
                    'nomor_tiket' => $p->nomor_tiket,
                    'nama' => $p->nama,
                    'kategori_pengaduan' => $p->kategori_pengaduan,
                    'isi_ringkas' => mb_substr($p->isi_pengaduan, 0, 80),
                    'dibuat' => $p->created_at->diffForHumans(),
                ])
                ->all(),
        ];
    }
}
