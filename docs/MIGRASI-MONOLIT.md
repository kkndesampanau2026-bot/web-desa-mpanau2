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

### ⬜ Fase 3 — Sisa halaman publik

- [ ] Infografis: Penduduk, APBDes, Stunting, IDM, SDGs, Bansos (+ tata letak
      sub-tab)
- [ ] Ekonomi: Potensi, Wisata (daftar + detail), Belanja (daftar + detail)
- [ ] PPID: beranda, dasar hukum, informasi berkala/serta-merta/setiap-saat,
      permohonan + pelacakan
- [ ] Pengaduan: formulir + pelacakan
- [ ] Peta (Leaflet)
- [ ] Halaman galat Inertia (404/403/500) — saat ini masih halaman galat Laravel

Formulir publik (cek bansos, permohonan PPID, pengaduan) berpindah dari
`axios.post` ke `useForm` Inertia. CAPTCHA Turnstile ikut pada langkah ini.

### ⬜ Fase 4 — Dashboard CMS

- [ ] Profil Desa, SOTK & BPD, Berita, Galeri, Pengaturan Umum
- [ ] Data Penduduk (termasuk impor CSV)
- [ ] Potensi & Ekonomi, Titik Lokasi, Pengaduan, Bansos, PPID
- [ ] Unggah berkas lewat `useForm` (Inertia mendukung `multipart` langsung)
- [ ] Menu `LayoutAdmin` dilengkapi seiring modul berpindah

### ⬜ Fase 5 — Pembersihan

- [ ] Hapus `routes/api.php` beserta `app/Http/Controllers/Api/`
- [ ] Hapus `$middleware->statefulApi()` dan `config/cors.php`
- [ ] Hapus workaround header `Origin` pada `tests/TestCase.php`
- [ ] Pindahkan 191 test dari asersi JSON ke `assertInertia`
- [ ] Hapus direktori `frontend/`
- [ ] Perbarui `README.md`, `docs/DEPLOYMENT.md`, `docs/DEVIASI.md`

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
