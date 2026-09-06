<?php

use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Admin\PenggunaController;
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
use App\Http\Controllers\Publik\SuratPengantarController;
use App\Http\Controllers\Publik\TelegramWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
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

    /*
    | Surat Pengantar RT/Dusun.
    |
    | Sepola dengan Pengaduan & PPID: formulir dan pelacakan berdiri sebagai
    | dua alamat terpisah, sehingga tautan hasil pelacakan dapat dibagikan
    | tanpa ikut membawa formulir kosong.
    */
    Route::get('/layanan-mandiri/surat-pengantar', [SuratPengantarController::class, 'formulir'])
        ->name('surat.pengantar');
    // Dibatasi lajunya meski hanya GET: halaman inilah yang membuka data
    // pemohon bila pasangan tiket + tanggal lahir tepat, sehingga ia adalah
    // permukaan penebakan — bukan sekadar halaman baca.
    Route::get('/layanan-mandiri/surat-pengantar/lacak', [SuratPengantarController::class, 'lacak'])
        ->middleware('throttle:cek-surat')
        ->name('surat.lacak');
});

/*
|--------------------------------------------------------------------------
| Formulir publik dengan pembatasan laju
|--------------------------------------------------------------------------
| Tidak ikut middleware `catat.kunjungan` karena bukan kunjungan halaman.
*/

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
| Surat Pengantar.
|
| Pengajuan dibatasi ketat: setiap kiriman yang berhasil melahirkan satu
| render PDF dan satu pesan Telegram ke ponsel Ketua RT. Tanpa rem ini,
| formulir yang sama dapat dipakai membanjiri ponsel pejabat desa.
*/
Route::post('/layanan-mandiri/surat-pengantar', [SuratPengantarController::class, 'ajukan'])
    ->middleware('throttle:kirim-surat')
    ->name('surat.kirim');

/*
| Unduh PDF surat.
|
| Menuntut tanggal lahir pemohon, bukan nomor tiket saja — lihat catatan pada
| SuratPengantarController::lacak(). Batas lajunya menahan penebakan pasangan
| tiket + tanggal lahir secara beruntun.
*/
Route::get('/layanan-mandiri/surat-pengantar/{tiket}/unduh', [SuratPengantarController::class, 'unduh'])
    ->middleware('throttle:cek-surat')
    ->name('surat.unduh');

/*
|--------------------------------------------------------------------------
| Webhook Telegram
|--------------------------------------------------------------------------
| Di luar grup mana pun: Telegram tidak membawa cookie sesi, tidak menerima
| pengalihan, dan tidak boleh dihitung sebagai kunjungan halaman.
|
| CSRF dikecualikan karena pengirimnya bukan peramban warga. Sebagai
| gantinya, keasliannya diperiksa lewat header rahasia dan seluruh wewenang
| ditegakkan ulang dari chat ID pengirim — lihat TelegramWebhookController.
*/
Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->middleware('throttle:telegram-webhook')
    ->name('telegram.webhook');

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
        ['surat', 'Surat', 'manage-letter-request'],
        ['pengaturan', 'Pengaturan', 'manage-settings'],
    ];

    foreach ($layar as [$jalur, $komponen, $izin]) {
        Route::get("/{$jalur}", fn () => Inertia::render("Admin/{$komponen}"))
            ->middleware("permission:{$izin}")
            ->name($jalur);
    }

    /*
    | Pengelolaan akun operator.
    |
    | Berbeda dari daftar di atas: datanya datang sebagai prop Inertia dari
    | controller, bukan lewat XHR ke /api/v1 — pola yang wajib diikuti modul
    | baru (lihat docs/MIGRASI-MONOLIT.md Fase 4).
    |
    | `manage-users` sengaja dijaga terpisah dari izin lain. Peran "Operator
    | Utama" memegang seluruh izin KECUALI ini, sebab yang dapat membuat akun
    | dapat membuat akun Admin Utama — dan dengan begitu memperoleh kembali
    | apa pun yang ditahan darinya.
    */
    Route::middleware('permission:manage-users')->group(function () {
        Route::get('/pengguna', [PenggunaController::class, 'index'])->name('pengguna');
        Route::post('/pengguna', [PenggunaController::class, 'store'])->name('pengguna.simpan');
        Route::put('/pengguna/{pengguna}', [PenggunaController::class, 'update'])
            ->name('pengguna.perbarui');
        Route::delete('/pengguna/{pengguna}', [PenggunaController::class, 'destroy'])
            ->name('pengguna.hapus');
    });

    /*
    | Akun sendiri — TANPA permission tambahan.
    |
    | Setiap operator harus dapat mengganti kata sandinya sendiri, termasuk
    | Operator Konten yang tidak berhak membuka satu pun layar grup Sistem.
    | Mensyaratkan `manage-users` di sini akan memaksa pilihan antara memberi
    | semua orang hak membuat akun, atau mengunci mereka dari kata sandinya
    | sendiri.
    */
    Route::get('/akun', [AkunController::class, 'tampilkan'])->name('akun');
    Route::put('/akun', [AkunController::class, 'perbarui'])->name('akun.perbarui');
    Route::put('/akun/sandi', [AkunController::class, 'ubahSandi'])->name('akun.sandi');
});
