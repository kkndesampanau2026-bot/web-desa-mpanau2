# Migrasi Arsitektur: Decoupled → Monolit Inertia

Catatan kerja perpindahan dari **headless/decoupled** (Laravel REST API + dua
SPA React terpisah) ke **monolit Laravel + Inertia.js + React**.

Dimulai 20 Agustus 2026. Dokumen ini adalah daftar periksa yang hidup —
perbarui saat sebuah modul selesai.

## Mengapa

Arsitektur lama menuntut tiga proses berjalan bersamaan (`:8000`, `:5173`,
`:5174`), tiga daftar rute yang harus tetap sepadan (`routes/api.php`,
`routes.tsx`, `App.tsx`), dan penyiapan lintas-origin yang rapuh: CORS,
`SANCTUM_STATEFUL_DOMAINS`, dan `SESSION_DOMAIN` harus cocok satu sama lain
atau login gagal tanpa pesan yang jelas.

Inertia menghapus lapisan itu tanpa membuang komponen React yang sudah ada:
controller Laravel mengirim data langsung sebagai prop halaman, tidak ada lagi
klien HTTP, cache sisi klien, maupun router sisi klien yang harus dipelihara.

## Bentuk akhir yang dituju

```
.                          ← Laravel, sekaligus root proyek
├── app/Http/Controllers/
│   ├── Publik/            ← controller halaman publik (Inertia::render)
│   ├── Admin/             ← controller dashboard CMS
│   └── Auth/              ← login berbasis sesi
├── resources/js/
│   ├── app.tsx            ← satu entry, menggantikan dua main.tsx
│   ├── Layouts/           ← LayoutPublik, LayoutAdmin
│   ├── Components/        ← komponen bersama kedua area
│   └── Pages/             ← Publik/*, Admin/*, Auth/*
├── routes/web.php         ← SATU-SATUNYA peta situs
└── docs/
```

`routes/api.php`, `frontend/`, dan seluruh konfigurasi CORS/Sanctum SPA hilang
di akhir migrasi.

## Status

### ✅ Fase 1 — Penggabungan repo & fondasi Inertia

- [x] Cadangan sebelum restrukturisasi:
      `d:/Khaechal/KKN/backup-web-desa-mpanau2-decoupled-20260820.tar.gz`
- [x] Laravel dipindahkan dari `backend/` ke root proyek
- [x] `inertiajs/inertia-laravel` v3 + `tightenco/ziggy` (composer)
- [x] Satu `package.json` menggantikan tiga; Redux dibuang (dependensi mati,
      tidak dipakai satu berkas pun)
- [x] `vite.config.ts` memakai `laravel-vite-plugin`; alias `@` → `resources/js`
- [x] `resources/css/app.css` menyatukan dua stylesheet; huruf dashboard
      dipisah sebagai token `--font-ui`
- [x] `resources/views/app.blade.php` + `resources/js/app.tsx`
- [x] `HandleInertiaRequests` membagikan `auth`, `pengaturan`,
      `statistik_kunjungan`, `flash`
- [x] `SESSION_DOMAIN=localhost` dihapus dari `.env` — warisan Sanctum SPA yang
      membuat setiap POST gagal 419 saat situs dibuka lewat `127.0.0.1`

### ✅ Fase 2 — Autentikasi sesi & modul Konten Inti

- [x] Login/logout berbasis sesi (`Auth\LoginController`), menggantikan alur
      Sanctum SPA tiga langkah (`/sanctum/csrf-cookie` → `/auth/login` →
      `/auth/me`)
- [x] `LayoutPublik` + `LayoutAdmin` (persistent layout Inertia)
- [x] Beranda, Profil, Pemerintah, Berita (daftar + detail), Galeri (daftar +
      album)
- [x] Dashboard admin (kerangka)
- [x] `tests/Feature/Publik/BerandaTest.php` — pola uji `assertInertia`

### ✅ Fase 3 — Sisa halaman publik

- [x] Infografis: Penduduk, APBDes, Stunting, Bansos, IDM, SDGs (+ tata letak
      sub-tab); `/infografis` mengalihkan ke sub-tab pertama
- [x] Ekonomi: Potensi, Wisata (daftar + detail), Belanja (daftar + detail)
- [x] PPID: beranda, dasar hukum, informasi berkala/serta-merta/setiap-saat,
      permohonan + pelacakan
- [x] Pengaduan: formulir (termasuk unggahan lampiran) + pelacakan
- [x] Peta Leaflet
- [x] Halaman galat Inertia (403/404/419/500/503) memakai kerangka situs
- [x] `tests/Feature/Publik/RuteHalamanTest.php` — menguji peta situs sebagai
      satu kesatuan, 24 halaman publik + 12 layar CMS

Formulir publik (cek bansos, permohonan PPID, pengaduan) kini memakai `useForm`
Inertia. Hasilnya dititipkan lewat flash session, bukan disimpan di state React:
menyegarkan halaman membuang hasil pencarian bansos maupun tanda terima — memang
disengaja, karena keduanya menyangkut data pribadi seseorang dan tidak
sepatutnya tetap terpampang di layar bersama kantor desa.

### 🟡 Fase 4 — Dashboard CMS

Kesebelas layar **sudah berjalan** di dalam monolit: route, kerangka, menu, dan
pagar permission per modul lengkap. Yang belum berpindah adalah **cara mereka
mengambil data** — masih XHR ke `/api/v1/admin/*`, belum prop Inertia.

- [x] Semua layar disajikan Laravel lewat Inertia beserta permission-nya:
      Profil, SOTK & BPD, Berita, Galeri, Data Penduduk, Potensi & Ekonomi,
      Titik Lokasi, Pengaduan, Bansos, PPID, Pengaturan Umum
- [x] Menu `LayoutAdmin` lengkap, disaring menurut permission
- [x] `Sanctum::currentRequestHost()` diaktifkan agar XHR same-origin tetap
      terautentikasi berapa pun port yang dipakai
- [ ] Ganti `useQuery` → prop Inertia pada tiap layar
- [ ] Ganti `useMutation` + axios → `useForm` Inertia
- [ ] Unggah berkas lewat `useForm` (Inertia mendukung `multipart` langsung)

Selama butir-butir itu belum selesai, `@tanstack/react-query`, `axios`,
`resources/js/lib/api.ts`, dan `routes/api.php` tetap dibutuhkan.

### ⬜ Fase 5 — Pembersihan

- [ ] Hapus `routes/api.php` beserta `app/Http/Controllers/Api/`
- [ ] Hapus `$middleware->statefulApi()`, `config/cors.php`, `config/sanctum.php`
- [ ] Hapus `resources/js/lib/api.ts`, `axios`, `@tanstack/react-query`
- [ ] Hapus workaround header `Origin` pada `tests/TestCase.php`
- [ ] Pindahkan test API yang tersisa ke asersi `assertInertia`
- [ ] Hapus direktori `frontend/`
- [ ] Perbarui `README.md`, `docs/DEPLOYMENT.md`, `docs/DEVIASI.md`

## Utang yang ditemukan selama migrasi

**CAPTCHA tidak pernah terpasang di sisi klien.** `CaptchaVerifier` dan
`config/captcha.php` sudah lengkap di server, tetapi tidak satu pun formulir
publik — dulu maupun sekarang — mengirimkan token Turnstile. Selama
`TURNSTILE_SECRET_KEY` kosong verifikasi dilewati sehingga tidak ada gejala;
begitu kunci itu diisi, **ketiga formulir publik akan berbalas 422**. Widget
Turnstile perlu dipasang lebih dulu sebelum kunci diaktifkan.

## Keputusan yang mengikat sisa pekerjaan

1. **Data baca lewat prop, bukan fetch.** Halaman menerima datanya dari
   controller. Tidak ada `useQuery` baru; React Query hanya bertahan selama
   masih ada layar CMS yang belum berpindah.
2. **Prop bersama tidak diambil ulang per halaman.** `pengaturan` dan
   `statistik_kunjungan` sudah ikut setiap respons publik — controller tidak
   boleh mengirimnya lagi.
3. **Menyembunyikan menu bukan kontrol akses.** Setiap route `/admin/*` wajib
   punya middleware permission sendiri, persis seperti endpoint API lama
   (PRD 12.2).
4. **Endpoint publik tetap tidak menyentuh data pribadi.** Pemisahan
   publik/admin pindah dari dua grup rute API menjadi dua grup rute web —
   bukan dilonggarkan (PRD 7.2, 12.2).
5. **Logika yang dipakai dua tempat diangkat ke service**, bukan disalin.
   Sudah dilakukan untuk `PengaturanSitus` dan `ProfilDesa`.

## Cara menjalankan setelah migrasi

```bash
npm run dev     # Laravel :8000 + Vite (aset) — dua proses, bukan tiga
```

Situs publik dan dashboard berada di satu alamat: `http://localhost:8000`
dan `http://localhost:8000/admin`.
