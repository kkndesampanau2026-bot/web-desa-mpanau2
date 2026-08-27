<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Publik\BerandaController;
use App\Http\Controllers\Publik\BeritaController;
use App\Http\Controllers\Publik\EkonomiController;
use App\Http\Controllers\Publik\GaleriController;
use App\Http\Controllers\Publik\InfografisController;
use App\Http\Controllers\Publik\LayananMandiriController;
use App\Http\Controllers\Publik\PengaduanController;
use App\Http\Controllers\Publik\PetaController;
use App\Http\Controllers\Publik\PpidController;
use App\Http\Controllers\Publik\ProfilController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Rute Halaman — Website Profil Desa Digital
|--------------------------------------------------------------------------
|
| Sejak arsitektur dipadukan menjadi monolit Inertia, berkas inilah satu-satunya
| peta situs. Sebelumnya alamat halaman hidup di `routes.tsx` (situs publik) dan
| `App.tsx` (dashboard) sementara alamat datanya hidup di `routes/api.php` —
| tiga daftar yang harus dijaga tetap sepadan setiap kali sebuah modul
| bertambah.
|
| Dua kelompok sengaja dipisah tegas, mengikuti PRD 7.2:
|
|   /...            halaman publik, tanpa autentikasi, hanya memuat data
|                   agregat/termoderasi.
|   /admin/...      dashboard CMS, wajib sesi + permission.
|
| Pemisahan ini penting karena modul sensitif (kependudukan, bansos,
| APBDes-realisasi) tidak boleh punya jalur publik sama sekali — bukan sekadar
| difilter di controller yang sama.
|
*/

/*
|--------------------------------------------------------------------------
| Publik
|--------------------------------------------------------------------------
| Middleware `catat.kunjungan` dipasang di tingkat grup agar modul yang
| ditambahkan berikutnya otomatis ikut terhitung (PRD 6.16).
*/
Route::middleware('catat.kunjungan')->group(function () {
    Route::get('/', BerandaController::class)->name('beranda');

    Route::get('/profil', [ProfilController::class, 'index'])->name('profil');
    Route::get('/pemerintah', [ProfilController::class, 'pemerintah'])->name('pemerintah');

    Route::get('/berita', [BeritaController::class, 'index'])->name('berita.index');
    Route::get('/berita/{slug}', [BeritaController::class, 'show'])->name('berita.show');

    Route::get('/galeri', [GaleriController::class, 'index'])->name('galeri.index');
    Route::get('/galeri/{slug}', [GaleriController::class, 'show'])->name('galeri.show');

    /*
    | Infografis — satu halaman induk dengan 6 sub-tab (PRD Bagian 11).
    | Induknya mengarahkan ke sub-tab pertama; tidak ada halaman "hub"
    | tersendiri, persis seperti sitemap PRD.
    */
    Route::redirect('/infografis', '/infografis/penduduk');

    Route::prefix('infografis')->name('infografis.')->group(function () {
        Route::get('/penduduk', [InfografisController::class, 'penduduk'])->name('penduduk');
        Route::get('/apbdes', [InfografisController::class, 'apbdes'])->name('apbdes');
        Route::get('/stunting', [InfografisController::class, 'stunting'])->name('stunting');
        Route::get('/bansos', [InfografisController::class, 'bansos'])->name('bansos');
        Route::get('/idm', [InfografisController::class, 'idm'])->name('idm');
        Route::get('/sdgs', [InfografisController::class, 'sdgs'])->name('sdgs');
    });

    /*
    | Potensi, Wisata & Katalog UMKM — PRD 6.11 & 6.12.
    |
    | Satu alamat untuk ketiganya. /potensi memuat seluruh kategori potensi
    | desa, dan dua di antaranya menampilkan modul yang sudah punya datanya
    | sendiri: `?kategori=Pariwisata` menampilkan daftar destinasi,
    | `?kategori=Ekonomi` menampilkan katalog UMKM.
    |
    | Halaman detailnya sengaja ikut tinggal di bawah /potensi. Sewaktu
    | destinasi dan produk punya alamat sendiri (/wisata/{slug} dan
    | /ekonomi/{slug}), menekan sebuah kartu memindahkan pengunjung ke rute
    | lain — dan tombol "kembali" di sana tidak punya cara mengetahui daftar
    | mana yang tadi dibuka, sehingga selalu memulangkan ke pangkal halaman
    | dan membuang kategori, kata kunci, serta nomor halaman yang sedang
    | aktif. Dengan slug berada di bawah /potensi, query string daftarnya ikut
    | terbawa dan tinggal dipasang kembali pada tautan kembalinya.
    */
    Route::get('/potensi', [EkonomiController::class, 'potensi'])->name('potensi');
    Route::get('/potensi/{slug}', [EkonomiController::class, 'potensiDetail'])
        ->name('potensi.show');

    /*
    | Alamat lama. Ketiganya pernah menjadi halaman tersendiri dan tautannya
    | sudah terlanjur dibagikan warga serta diindeks mesin pencari, jadi tetap
    | dijawab — kini sebagai pengalihan permanen ke kategori yang bersangkutan.
    */
    Route::redirect('/wisata', '/potensi?kategori=Pariwisata', 301);
    Route::get(
        '/wisata/{slug}',
        fn (string $slug) => redirect("/potensi/{$slug}?kategori=Pariwisata", 301)
    );

    Route::redirect('/ekonomi', '/potensi?kategori=Ekonomi', 301);
    Route::get(
        '/ekonomi/{slug}',
        fn (string $slug) => redirect("/potensi/{$slug}?kategori=Ekonomi", 301)
    );

    Route::redirect('/belanja', '/potensi?kategori=Ekonomi', 301);
    Route::get(
        '/belanja/{slug}',
        fn (string $slug) => redirect("/potensi/{$slug}?kategori=Ekonomi", 301)
    );

    /*
    | PPID — struktur mengikuti kategori baku UU No. 14/2008.
    */
    Route::prefix('ppid')->name('ppid.')->group(function () {
        Route::get('/', [PpidController::class, 'beranda'])->name('beranda');
        Route::get('/dasar-hukum', [PpidController::class, 'dasarHukum'])->name('dasar-hukum');

        // Pelacakan didaftarkan SEBELUM {jenis}, jika tidak "permintaan" akan
        // tertangkap lebih dulu sebagai jenis informasi dan berakhir 404.
        Route::get('/permintaan', [PpidController::class, 'formulirPermohonan'])
            ->name('permintaan');
        Route::get('/permintaan/lacak', [PpidController::class, 'lacak'])->name('lacak');

        Route::get('/{jenis}', [PpidController::class, 'informasi'])->name('informasi');
    });

    /*
    | Pengaduan Masyarakat — PRD 6.15.
    */
    Route::get('/pengaduan', [PengaduanController::class, 'formulir'])->name('pengaduan');
    Route::get('/pengaduan/lacak', [PengaduanController::class, 'lacak'])
        ->name('pengaduan.lacak');

    /*
    | Peta Desa & Titik Lokasi — PRD 6.9.
    */
    Route::get('/listing', [PetaController::class, 'listing'])->name('listing');

    /*
    | Layanan Mandiri — laman pengarah yang mengumpulkan layanan warga.
    */
    Route::get('/layanan-mandiri', LayananMandiriController::class)->name('layanan-mandiri');
});

/*
|--------------------------------------------------------------------------
| Formulir publik dengan pembatasan laju
|--------------------------------------------------------------------------
| Tidak ikut middleware `catat.kunjungan` karena bukan kunjungan halaman.
*/

// Cek Penerima Bansos — 10 permintaan/menit per IP, sesuai PRD 6.6.
// Batas ini yang menahan fitur pencarian dari dipakai mengenumerasi
// daftar penerima bantuan warga sedesa.
Route::post('/infografis/bansos/cek', [InfografisController::class, 'cekPenerima'])
    ->middleware('throttle:cek-bansos')
    ->name('infografis.bansos.cek');

// Pengajuan permohonan informasi — dibatasi agar formulir publik tidak
// dipakai membanjiri meja PPID desa.
Route::post('/ppid/permintaan', [PpidController::class, 'ajukanPermohonan'])
    ->middleware('throttle:5,1')
    ->name('ppid.permintaan.kirim');

// Pengaduan menerima unggahan berkas, sehingga batasnya lebih ketat —
// formulir ini jalur termudah untuk membanjiri penyimpanan server.
Route::post('/pengaduan', [PengaduanController::class, 'ajukan'])
    ->middleware('throttle:3,1')
    ->name('pengaduan.kirim');

/*
|--------------------------------------------------------------------------
| Autentikasi operator
|--------------------------------------------------------------------------
| Route `login` WAJIB bernama persis itu: middleware `Authenticate` bawaan
| Laravel mengalihkan tamu ke sana saat menyusun AuthenticationException.
*/
Route::middleware('guest')->group(function () {
    Route::get('/admin/masuk', [LoginController::class, 'tampilkan'])->name('login');
    Route::post('/admin/masuk', [LoginController::class, 'masuk'])
        // Rem percobaan brute-force kredensial, sama seperti endpoint lama.
        ->middleware('throttle:6,1');
});

Route::post('/admin/keluar', [LoginController::class, 'keluar'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Dashboard CMS
|--------------------------------------------------------------------------
| Otorisasi per modul dipasang pada masing-masing route, bukan hanya di menu:
| menyembunyikan tautan bukan kontrol akses (PRD 12.2).
*/
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');

    /*
    | Tiap layar dijaga permission-nya masing-masing. Menyembunyikan menu di
    | sidebar hanyalah kenyamanan — pagar sesungguhnya ada di baris-baris ini,
    | dan di endpoint /api/v1/admin/* yang dipanggil layar bersangkutan.
    |
    | Layar-layar ini masih mengambil datanya lewat XHR ke API, belum sebagai
    | prop Inertia. Route di sini hanya menyajikan halamannya.
    */
    $layar = [
        ['profil', 'Profil', 'manage-village-profile'],
        ['sotk-bpd', 'Sotk', 'manage-officials'],
        ['berita', 'Berita', 'manage-news'],
        ['galeri', 'Galeri', 'manage-gallery'],
        ['penduduk', 'Penduduk', 'manage-population-data'],
        ['apbdes', 'Apbdes', 'manage-apbdes'],
        ['stunting', 'Stunting', 'manage-stunting'],
        ['idm', 'Idm', 'manage-idm'],
        ['sdgs', 'Sdgs', 'manage-sdgs'],
        ['ekonomi', 'Ekonomi', 'manage-potential'],
        ['peta', 'Peta', 'manage-poi'],
        ['pengaduan', 'Pengaduan', 'respond-complaint'],
        ['bansos', 'Bansos', 'manage-bansos'],
        ['ppid', 'Ppid', 'manage-ppid-content'],
        ['pengaturan', 'Pengaturan', 'manage-settings'],
    ];

    foreach ($layar as [$jalur, $komponen, $izin]) {
        Route::get("/{$jalur}", fn () => Inertia::render("Admin/{$komponen}"))
            ->middleware("permission:{$izin}")
            ->name($jalur);
    }
});
