<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Publik\BerandaController;
use App\Http\Controllers\Publik\BeritaController;
use App\Http\Controllers\Publik\GaleriController;
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
});

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
});
