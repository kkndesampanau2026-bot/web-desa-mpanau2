<?php

use App\Http\Controllers\Api\V1\Admin\BansosController as AdminBansosController;
use App\Http\Controllers\Api\V1\Admin\BpdMemberController as AdminBpdMemberController;
use App\Http\Controllers\Api\V1\Admin\BudgetController;
use App\Http\Controllers\Api\V1\Admin\EkonomiController as AdminEkonomiController;
use App\Http\Controllers\Api\V1\Admin\GalleryController as AdminGalleryController;
use App\Http\Controllers\Api\V1\Admin\IndeksDesaController;
use App\Http\Controllers\Api\V1\Admin\NewsController as AdminNewsController;
use App\Http\Controllers\Api\V1\Admin\OfficialController as AdminOfficialController;
use App\Http\Controllers\Api\V1\Admin\PengaduanController as AdminPengaduanController;
use App\Http\Controllers\Api\V1\Admin\PetaController as AdminPetaController;
use App\Http\Controllers\Api\V1\Admin\PpidController as AdminPpidController;
use App\Http\Controllers\Api\V1\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Api\V1\Admin\ResidentController;
use App\Http\Controllers\Api\V1\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\V1\Admin\SuratPengantarController as AdminSuratController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Public\BansosController;
use App\Http\Controllers\Api\V1\Public\EkonomiController;
use App\Http\Controllers\Api\V1\Public\GalleryController;
use App\Http\Controllers\Api\V1\Public\InfografisController;
use App\Http\Controllers\Api\V1\Public\NewsController;
use App\Http\Controllers\Api\V1\Public\PengaduanController;
use App\Http\Controllers\Api\V1\Public\PetaController;
use App\Http\Controllers\Api\V1\Public\PpidController;
use App\Http\Controllers\Api\V1\Public\ProfileController;
use App\Http\Controllers\Api\V1\Public\SettingController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — Website Profil Desa Digital
|--------------------------------------------------------------------------
|
| Struktur mengikuti PRD Bagian 9. Dua kelompok yang sengaja dipisah tegas:
|
|   /api/v1/...          endpoint publik, tanpa autentikasi, hanya memuat
|                        data agregat/termoderasi (PRD 7.2).
|   /api/v1/admin/...    endpoint admin, wajib sesi Sanctum + permission.
|
| Pemisahan ini penting karena modul sensitif (kependudukan, bansos,
| APBDes-realisasi) tidak boleh punya jalur publik sama sekali — bukan sekadar
| difilter di controller yang sama.
|
*/

Route::prefix('v1')->group(function () {

    Route::get('/health', fn () => ApiResponse::success([
        'service' => config('app.name'),
        'version' => 'v1',
        'time' => now()->toIso8601String(),
    ]));

    // --- Autentikasi admin ------------------------------------------------
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')   // rem percobaan brute-force kredensial
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    });

    /*
    |--------------------------------------------------------------------
    | Publik (Fase 2)
    |--------------------------------------------------------------------
    | Middleware `catat.kunjungan` dipasang di tingkat grup agar modul yang
    | ditambahkan pada fase berikutnya otomatis ikut terhitung (PRD 6.16).
    */
    Route::middleware('catat.kunjungan')->group(function () {
        Route::get('/profil', [ProfileController::class, 'profil']);
        Route::get('/officials', [ProfileController::class, 'officials']);
        Route::get('/bpd-members', [ProfileController::class, 'bpdMembers']);

        Route::get('/berita', [NewsController::class, 'index']);
        Route::get('/berita/kategori', [NewsController::class, 'kategori']);
        // Didaftarkan SETELAH /berita/kategori agar segmen literal tidak
        // tertelan oleh parameter {slug}.
        Route::get('/berita/{slug}', [NewsController::class, 'show']);

        Route::get('/galeri', [GalleryController::class, 'index']);
        Route::get('/galeri/{slug}', [GalleryController::class, 'show']);

        Route::get('/settings', [SettingController::class, 'index']);

        // --- Infografis (Fase 3) ---
        // Seluruh endpoint di bawah membaca data AGREGAT saja; tidak satu pun
        // menyentuh tabel `residents` (PRD 7.2 & 12.2).
        Route::get('/infografis/penduduk', [InfografisController::class, 'penduduk']);
        Route::get('/infografis/apbdes', [InfografisController::class, 'apbdes']);
        Route::get('/infografis/stunting', [InfografisController::class, 'stunting']);
        Route::get('/infografis/idm', [InfografisController::class, 'idm']);
        Route::get('/infografis/sdgs', [InfografisController::class, 'sdgs']);

        // --- Bansos & PPID (Fase 4) ---
        Route::get('/infografis/bansos', [BansosController::class, 'infografis']);

        Route::get('/ppid/dasar-hukum', [PpidController::class, 'dasarHukum']);
        Route::get('/ppid/informasi', [PpidController::class, 'informasi']);
        Route::get('/ppid/permintaan/{nomorRegistrasi}/status', [PpidController::class, 'lacakPermohonan']);

        // --- Potensi, Wisata & UMKM (Fase 5) ---
        Route::get('/potensi', [EkonomiController::class, 'potensi']);
        Route::get('/potensi/{slug}', [EkonomiController::class, 'potensiDetail']);
        Route::get('/wisata', [EkonomiController::class, 'wisata']);
        Route::get('/wisata/{slug}', [EkonomiController::class, 'wisataDetail']);
        // Didaftarkan SEBELUM /produk/{slug} agar segmen literal tidak
        // tertelan oleh parameter.
        Route::get('/produk/kategori', [EkonomiController::class, 'kategoriProduk']);
        Route::get('/produk', [EkonomiController::class, 'produk']);
        Route::get('/produk/{slug}', [EkonomiController::class, 'produkDetail']);

        // --- Peta & Pengaduan (Fase 6) ---
        Route::get('/points-of-interest', [PetaController::class, 'titikLokasi']);
        Route::get('/pengaduan/kategori', [PengaduanController::class, 'kategori']);
        Route::get('/pengaduan/{nomorTiket}/status', [PengaduanController::class, 'lacak']);
    });

    /*
    |--------------------------------------------------------------------
    | Formulir publik dengan pembatasan laju
    |--------------------------------------------------------------------
    | Tidak ikut middleware `catat.kunjungan` karena bukan kunjungan halaman.
    */

    // Cek Penerima Bansos — 10 permintaan/menit per IP, sesuai PRD 6.6.
    // Batas ini yang menahan fitur pencarian dari dipakai mengenumerasi
    // daftar penerima bantuan warga sedesa.
    Route::post('/infografis/bansos/cek', [BansosController::class, 'cekPenerima'])
        ->middleware('throttle:cek-bansos');

    // Pengajuan permohonan informasi — dibatasi agar formulir publik tidak
    // dipakai membanjiri meja PPID desa.
    Route::post('/ppid/permintaan', [PpidController::class, 'ajukanPermohonan'])
        ->middleware('throttle:5,1');

    // Pengaduan menerima unggahan berkas, sehingga batasnya lebih ketat —
    // formulir ini jalur termudah untuk membanjiri penyimpanan server.
    Route::post('/pengaduan', [PengaduanController::class, 'ajukan'])
        ->middleware('throttle:3,1');

    // Tidak ikut dicatat: dipanggil oleh widget di setiap halaman, sehingga
    // akan menggandakan hitungan kunjungan atas halaman yang sama.
    Route::get('/visitor-stats', [SettingController::class, 'visitorStats']);

    /*
    |--------------------------------------------------------------------
    | Admin (Fase 2)
    |--------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware('auth:sanctum')->group(function () {

        Route::get('/ping', fn () => ApiResponse::success(['pong' => true]))
            ->middleware('permission:view-dashboard');

        // Profil Desa & SOTK
        Route::middleware('permission:manage-village-profile')->group(function () {
            Route::get('/profil', [AdminProfileController::class, 'show']);
            Route::put('/profil', [AdminProfileController::class, 'update']);
        });

        Route::middleware('permission:manage-officials')->group(function () {
            Route::apiResource('officials', AdminOfficialController::class)
                ->except(['show']);
            Route::apiResource('bpd-members', AdminBpdMemberController::class)
                ->parameters(['bpd-members' => 'bpdMember'])
                ->except(['show']);
        });

        // Berita
        Route::middleware('permission:manage-news')->group(function () {
            Route::apiResource('berita', AdminNewsController::class)
                ->parameters(['berita' => 'news'])
                ->names('admin.berita');
        });

        // Pengaturan Umum
        Route::middleware('permission:manage-settings')->group(function () {
            Route::get('/settings', [AdminSettingController::class, 'show']);
            Route::put('/settings', [AdminSettingController::class, 'update']);
        });

        /*
        |------------------------------------------------------------------
        | Fase 3 — Data & Transparansi
        |------------------------------------------------------------------
        */

        // Data Penduduk — modul paling sensitif (PRD 12.2).
        Route::middleware('permission:manage-population-data')->group(function () {
            Route::get('/residents/opsi', [ResidentController::class, 'opsi']);
            Route::get('/residents', [ResidentController::class, 'index']);
            Route::post('/residents', [ResidentController::class, 'store']);
            Route::put('/residents/{resident}', [ResidentController::class, 'update']);
            Route::delete('/residents/{resident}', [ResidentController::class, 'destroy']);
            Route::get('/residents/template-csv', [ResidentController::class, 'templateCsv']);
            Route::post('/residents/import', [ResidentController::class, 'importCsv']);
        });

        // Membuka NIK utuh menuntut permission TERSENDIRI, terpisah dari hak
        // mengelola baris data — lihat RolePermissionSeeder & PRD 12.2.
        Route::get('/residents/{resident}', [ResidentController::class, 'show'])
            ->middleware('permission:view-population-pii');

        // APBDes
        Route::middleware('permission:manage-apbdes')->group(function () {
            Route::get('/apbdes/tahun', [BudgetController::class, 'tahun']);
            Route::post('/apbdes/tahun', [BudgetController::class, 'simpanTahun']);
            Route::put('/apbdes/tahun/{budgetYear}', [BudgetController::class, 'ubahTahun']);
            Route::delete('/apbdes/tahun/{budgetYear}', [BudgetController::class, 'hapusTahun']);
            Route::get('/apbdes/kategori', [BudgetController::class, 'kategori']);
            Route::post('/apbdes/kategori', [BudgetController::class, 'simpanKategori']);
            Route::put('/apbdes/kategori/{budgetCategory}', [BudgetController::class, 'ubahKategori']);
            Route::delete('/apbdes/kategori/{budgetCategory}', [BudgetController::class, 'hapusKategori']);
            Route::get('/apbdes/items', [BudgetController::class, 'items']);
            Route::post('/apbdes/items', [BudgetController::class, 'simpanItem']);
            Route::put('/apbdes/items/{budgetItem}', [BudgetController::class, 'ubahItem']);
            Route::delete('/apbdes/items/{budgetItem}', [BudgetController::class, 'hapusItem']);
        });

        // Stunting
        Route::middleware('permission:manage-stunting')->group(function () {
            Route::get('/stunting/dusuns', [IndeksDesaController::class, 'dusuns']);
            Route::get('/stunting', [IndeksDesaController::class, 'stuntingIndex']);
            Route::post('/stunting', [IndeksDesaController::class, 'stuntingSimpan']);
            Route::delete('/stunting/{stuntingRecord}', [IndeksDesaController::class, 'stuntingHapus']);
        });

        // IDM
        Route::middleware('permission:manage-idm')->group(function () {
            Route::get('/idm', [IndeksDesaController::class, 'idmIndex']);
            Route::post('/idm', [IndeksDesaController::class, 'idmSimpan']);
            Route::put('/idm/{idmScore}/indikator', [IndeksDesaController::class, 'idmIndikatorSimpan']);
            Route::delete('/idm/{idmScore}', [IndeksDesaController::class, 'idmHapus']);
        });

        // SDGs Desa
        Route::middleware('permission:manage-sdgs')->group(function () {
            Route::get('/sdgs', [IndeksDesaController::class, 'sdgsIndex']);
            Route::post('/sdgs', [IndeksDesaController::class, 'sdgsSimpan']);
            Route::delete('/sdgs/{tahun}', [IndeksDesaController::class, 'sdgsHapus'])
                ->whereNumber('tahun');
        });

        /*
        |------------------------------------------------------------------
        | Fase 4 — Bansos & PPID
        |------------------------------------------------------------------
        */

        // Bantuan Sosial
        Route::middleware('permission:manage-bansos')->group(function () {
            Route::get('/bansos/jenis', [AdminBansosController::class, 'jenisIndex']);
            Route::post('/bansos/jenis', [AdminBansosController::class, 'jenisSimpan']);
            Route::put('/bansos/jenis/{bansosType}', [AdminBansosController::class, 'jenisUbah']);
            Route::delete('/bansos/jenis/{bansosType}', [AdminBansosController::class, 'jenisHapus']);

            Route::get('/bansos/penerima', [AdminBansosController::class, 'penerimaIndex']);
            Route::post('/bansos/penerima', [AdminBansosController::class, 'penerimaSimpan']);
            Route::put('/bansos/penerima/{bansosRecipient}', [AdminBansosController::class, 'penerimaUbah']);
            Route::delete('/bansos/penerima/{bansosRecipient}', [AdminBansosController::class, 'penerimaHapus']);

            Route::get('/bansos/pantau-pencarian', [AdminBansosController::class, 'pantauPencarian']);
        });

        // Membuka NIK penerima utuh menuntut permission tersendiri.
        Route::get('/bansos/penerima/{bansosRecipient}', [AdminBansosController::class, 'penerimaShow'])
            ->middleware('permission:view-bansos-pii');

        // PPID — konten informasi publik
        Route::middleware('permission:manage-ppid-content')->group(function () {
            Route::get('/ppid/dasar-hukum', [AdminPpidController::class, 'dasarHukumIndex']);
            Route::post('/ppid/dasar-hukum', [AdminPpidController::class, 'dasarHukumSimpan']);
            // Didaftarkan sebagai PUT seperti modul lain. Klien mengirimnya
            // sebagai POST + `_method=PUT` karena PDF dikirim multipart —
            // Laravel memulihkan method aslinya sebelum routing, sehingga
            // route PUT inilah yang tercocokkan.
            Route::put('/ppid/dasar-hukum/{ppidLegalBasis}', [AdminPpidController::class, 'dasarHukumUbah']);
            Route::delete('/ppid/dasar-hukum/{ppidLegalBasis}', [AdminPpidController::class, 'dasarHukumHapus']);

            Route::get('/ppid/informasi', [AdminPpidController::class, 'informasiIndex']);
            Route::post('/ppid/informasi', [AdminPpidController::class, 'informasiSimpan']);
            Route::put('/ppid/informasi/{ppidInformationItem}', [AdminPpidController::class, 'informasiUbah']);
            Route::delete('/ppid/informasi/{ppidInformationItem}', [AdminPpidController::class, 'informasiHapus']);
        });

        // PPID — permohonan informasi dari warga
        Route::middleware('permission:respond-ppid-request')->group(function () {
            Route::get('/ppid/permohonan', [AdminPpidController::class, 'permohonanIndex']);
            Route::get('/ppid/permohonan/{ppidRequest}', [AdminPpidController::class, 'permohonanShow']);
            Route::put('/ppid/permohonan/{ppidRequest}', [AdminPpidController::class, 'permohonanTanggapi']);
        });

        /*
        |------------------------------------------------------------------
        | Fase 5 — Potensi & Ekonomi
        |------------------------------------------------------------------
        */

        Route::middleware('permission:manage-gallery')->group(function () {
            Route::get('/galeri', [AdminGalleryController::class, 'index']);
            Route::post('/galeri', [AdminGalleryController::class, 'store']);
            Route::get('/galeri/{gallery}', [AdminGalleryController::class, 'show']);
            // Form yang menyertakan berkas harus dikirim sebagai POST dengan
            // field `_method=PUT`: PHP tidak mengurai body multipart pada
            // request PUT, sedangkan method spoofing Laravel menanganinya.
            Route::put('/galeri/{gallery}', [AdminGalleryController::class, 'update']);
            Route::delete('/galeri/{gallery}', [AdminGalleryController::class, 'destroy']);

            Route::post('/galeri/{gallery}/foto', [AdminGalleryController::class, 'unggahFoto']);
            Route::put('/galeri/{gallery}/foto/{photo}', [AdminGalleryController::class, 'ubahFoto']);
            Route::delete('/galeri/{gallery}/foto/{photo}', [AdminGalleryController::class, 'hapusFoto']);
        });

        Route::middleware('permission:manage-potential')->group(function () {
            Route::get('/potensi', [AdminEkonomiController::class, 'potensiIndex']);
            Route::post('/potensi', [AdminEkonomiController::class, 'potensiSimpan']);
            Route::put('/potensi/{potential}', [AdminEkonomiController::class, 'potensiUbah']);
            Route::delete('/potensi/{potential}', [AdminEkonomiController::class, 'potensiHapus']);
        });

        Route::middleware('permission:manage-tourism')->group(function () {
            Route::get('/wisata', [AdminEkonomiController::class, 'wisataIndex']);
            Route::post('/wisata', [AdminEkonomiController::class, 'wisataSimpan']);
            Route::put('/wisata/{tourismSpot}', [AdminEkonomiController::class, 'wisataUbah']);
            Route::delete('/wisata/{tourismSpot}', [AdminEkonomiController::class, 'wisataHapus']);
            Route::post('/wisata/{tourismSpot}/foto', [AdminEkonomiController::class, 'wisataFotoUnggah']);
            Route::delete(
                '/wisata/{tourismSpot}/foto/{photo}',
                [AdminEkonomiController::class, 'wisataFotoHapus']
            );
        });

        Route::middleware('permission:manage-product')->group(function () {
            Route::get('/produk', [AdminEkonomiController::class, 'produkIndex']);
            Route::post('/produk', [AdminEkonomiController::class, 'produkSimpan']);
            Route::put('/produk/{product}', [AdminEkonomiController::class, 'produkUbah']);
            Route::delete('/produk/{product}', [AdminEkonomiController::class, 'produkHapus']);
            Route::post('/produk/{product}/foto', [AdminEkonomiController::class, 'produkFotoUnggah']);
            Route::delete(
                '/produk/{product}/foto/{photo}',
                [AdminEkonomiController::class, 'produkFotoHapus']
            );
        });

        /*
        |------------------------------------------------------------------
        | Fase 6 — Interaksi Publik & Peta
        |------------------------------------------------------------------
        */

        Route::middleware('permission:respond-complaint')->group(function () {
            Route::get('/pengaduan', [AdminPengaduanController::class, 'index']);
            // Didaftarkan SEBELUM /pengaduan/{complaint} agar segmen literal
            // tidak tertelan oleh parameter.
            Route::get('/pengaduan/rekap', [AdminPengaduanController::class, 'rekap']);
            Route::get('/pengaduan/{complaint}', [AdminPengaduanController::class, 'show']);
            Route::put('/pengaduan/{complaint}', [AdminPengaduanController::class, 'tanggapi']);
            Route::get(
                '/pengaduan/{complaint}/lampiran/{attachment}',
                [AdminPengaduanController::class, 'unduhLampiran']
            );
        });

        /*
        |------------------------------------------------------------------
        | Surat Pengantar — master & pemantauan
        |------------------------------------------------------------------
        | Dua permission terpisah dengan sengaja. `manage-letter-request`
        | cukup untuk memantau pengajuan dan membuat ulang PDF yang gagal;
        | `manage-letter-official` memegang chat ID Telegram dan tanda tangan
        | pejabat — yakni kunci yang menentukan siapa dapat menyetujui surat
        | atas nama desa. Yang kedua tidak boleh ikut terbawa hanya karena
        | seseorang diberi tugas memantau antrean surat.
        */
        Route::middleware('permission:manage-letter-request')->group(function () {
            Route::get('/surat/pengajuan', [AdminSuratController::class, 'daftarPengajuan']);
            Route::get('/surat/pengajuan/{letterRequest}', [AdminSuratController::class, 'lihatPengajuan']);
            Route::post('/surat/pengajuan/{letterRequest}/pdf', [AdminSuratController::class, 'buatUlangPdf']);
            Route::get('/surat/pengajuan/{letterRequest}/unduh', [AdminSuratController::class, 'unduhPdf']);
            Route::get('/surat/dusun', [AdminSuratController::class, 'daftarDusun']);
            Route::get('/surat/rt', [AdminSuratController::class, 'daftarRt']);
        });

        Route::middleware('permission:manage-letter-official')->group(function () {
            Route::post('/surat/rt', [AdminSuratController::class, 'simpanRt']);
            Route::put('/surat/rt/{rt}', [AdminSuratController::class, 'ubahRt']);
            Route::delete('/surat/rt/{rt}', [AdminSuratController::class, 'hapusRt']);

            Route::get('/surat/pejabat', [AdminSuratController::class, 'daftarPejabat']);
            Route::post('/surat/pejabat', [AdminSuratController::class, 'simpanPejabat']);
            // POST, bukan PUT: unggahan berkas multipart tidak terbaca
            // Laravel pada permintaan PUT. Layar CMS mengirim _method=PUT
            // seperti modul lain yang punya unggahan.
            Route::post('/surat/pejabat/{letterOfficial}', [AdminSuratController::class, 'ubahPejabat']);
            Route::delete('/surat/pejabat/{letterOfficial}', [AdminSuratController::class, 'hapusPejabat']);
            Route::get('/surat/pejabat/{letterOfficial}/ttd', [AdminSuratController::class, 'lihatTandaTangan']);
        });

        Route::middleware('permission:manage-poi')->group(function () {
            Route::get('/points-of-interest', [AdminPetaController::class, 'index']);
            Route::post('/points-of-interest', [AdminPetaController::class, 'simpan']);
            Route::put('/points-of-interest/{pointOfInterest}', [AdminPetaController::class, 'ubah']);
            Route::delete('/points-of-interest/{pointOfInterest}', [AdminPetaController::class, 'hapus']);
        });
    });
});