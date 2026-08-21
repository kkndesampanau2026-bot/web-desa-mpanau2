# Website Profil Desa Digital

Implementasi PRD v2.0 — Website Profil Desa Mpanau, Kec. Sigi Biromaru, Kab. Sigi, Sulawesi Tengah.

Arsitektur **headless/decoupled**: Laravel sebagai REST API murni, dua aplikasi React
terpisah sebagai klien.

## Struktur Monorepo

```
.
├── backend/                Laravel 12 — REST API (/api/v1)
├── frontend/
│   ├── public-app/         React — situs publik desa (port 5173)
│   └── admin-app/          React — dashboard CMS (port 5174)
└── docs/                   PRD, catatan arsitektur & panduan deployment
```

Dokumen penting:

| Berkas | Isi |
|---|---|
| `docs/DEVIASI.md` | Setiap perbedaan dari PRD beserta alasannya — **butuh persetujuan pemilik produk** |
| `docs/DEPLOYMENT.md` | Daftar periksa produksi; bagian A wajib sebelum go-live |

## Prasyarat

| Kebutuhan | Versi terpasang | Catatan |
|---|---|---|
| PHP | 8.2.12 | Ekstensi wajib: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip`, `gd` |
| Composer | 2.8.4 | |
| Node.js | 22.12 | |
| MySQL | 8.4.3 | Dari Laragon, bukan MariaDB XAMPP — lihat `docs/DEVIASI.md` |

## Menjalankan (development)

Tiga proses, masing-masing di terminal terpisah:

```bash
# 1. Backend API  → http://localhost:8000
cd backend && php artisan serve

# 2. Situs publik → http://localhost:5173
cd frontend/public-app && npm run dev

# 3. Dashboard    → http://localhost:5174
cd frontend/admin-app && npm run dev
```

Port frontend **tidak boleh diubah sembarangan**: nilainya terdaftar pada
`allowed_origins` (CORS) dan `SANCTUM_STATEFUL_DOMAINS` di `backend/.env`.
Mengubah salah satu tanpa yang lain akan membuat login gagal.

## Setup awal dari nol

```bash
# Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
# Kunci HMAC untuk blind index NIK/No. KK — WAJIB, terpisah dari APP_KEY.
php artisan pii:key
# buat database lebih dulu: CREATE DATABASE desa_mpanau;
php artisan migrate --seed

# Frontend
cd ../frontend/public-app && npm install && cp .env.example .env
cd ../admin-app        && npm install && cp .env.example .env
```

## Akun development

Dibuat oleh `AdminUserSeeder`, **hanya di lingkungan non-produksi**.
Kata sandi dapat diatur lewat `SEED_ADMIN_PASSWORD` (default: `password`).

| Email | Role | Cakupan akses |
|---|---|---|
| `superadmin@desa.test` | Super Admin | Seluruh modul (via `Gate::before`) |
| `admin@desa.test` | Admin Utama | Seluruh modul termasuk data pribadi |
| `konten@desa.test` | Operator Konten | Berita, galeri, potensi, wisata, produk, POI, pengaduan |
| `ppid@desa.test` | Operator PPID | Modul PPID & permohonan informasi (tanpa akses pengaduan) |

## Pengujian

```bash
# Backend — 169 test (MySQL)
cd backend && php artisan test

# Frontend publik — smoke test render (Vitest + Testing Library)
cd frontend/public-app && npm test
```

### Kunci data pribadi

Modul Kependudukan memerlukan `PII_HASH_KEY` — kunci HMAC yang membentuk
blind index NIK & No. KK (lihat `docs/DEVIASI.md` C1). Pada instalasi baru:

```bash
cd backend && php artisan pii:key
```

Kunci ini **terpisah dari `APP_KEY`** dan tidak boleh di-commit. Menggantinya
membuat blind index lama tidak cocok, sehingga pencarian NIK berhenti
berfungsi sampai data di-index ulang.

Test backend berjalan pada database MySQL terpisah (`desa_mpanau_test`), bukan
SQLite in-memory — lihat alasannya di `docs/DEVIASI.md`.

## Prinsip yang mengikat implementasi

Tiga aturan berikut berasal langsung dari PRD dan berlaku untuk semua modul
yang ditambahkan di fase berikutnya:

1. **Endpoint publik tidak pernah menyentuh data pribadi.** Modul sensitif
   (kependudukan, bansos, APBDes-realisasi) hanya punya jalur `/api/v1/admin/*`
   yang berpagar token + permission. Endpoint publik hanya mengembalikan
   agregat. (PRD 7.2, 12.2)
2. **Perubahan & akses data sensitif wajib tercatat** lewat `ActivityLogger`.
   Field PII disaring otomatis agar audit trail tidak menjadi tabel bayangan
   berisi NIK. (PRD 12.2)
3. **Setiap modul publik wajib punya empty-state informatif** — judul modul,
   penjelasan fungsinya, dan "Belum Ada Data". Bukan halaman kosong atau error.
   (PRD 3.2)

## Status Fase

Mengikuti milestone PRD Bagian 13.

- [x] **Fase 1 — Fondasi**: setup Laravel + 2 React app, autentikasi Sanctum,
      RBAC 4 role/25 permission, skema DB inti (`villages`, `dusuns`,
      `activity_logs`, perluasan `users`), kontrak API, audit trail, kerangka
      rute publik dengan empty-state.
- [x] **Fase 2 — Konten Inti**: Profil Desa (+ geografis & koordinat), SOTK &
      BPD, Berita (slug, penjadwalan, sanitasi HTML, meta SEO), Galeri (album +
      lightbox), Pengaturan Umum, Statistik Kunjungan. Mencakup 7 tabel baru,
      endpoint publik + CMS admin, dan halaman React di kedua aplikasi.
- [x] **Fase 3 — Data & Transparansi**: Data Penduduk (NIK terenkripsi +
      blind index, impor CSV per-baris, agregasi snapshot), APBDes, Stunting,
      IDM (+ tabel indikator), SDGs Desa. Halaman infografis publik dengan
      grafik, dan CMS Data Penduduk beserta impor CSV.
      *Belum ada:* halaman CMS untuk APBDes/Stunting/IDM/SDGs — API-nya sudah
      lengkap & teruji, antarmukanya menyusul.
- [x] **Fase 4 — Bansos & PPID**: Infografis Bansos + fitur Cek Penerima
      (rate limit 10/menit, pencocokan nama + 4 digit NIK, penyamaran nama,
      pesan netral, log anti-scraping tanpa menyimpan yang dicari). PPID
      lengkap: dasar hukum, informasi berkala/serta-merta/setiap-saat,
      formulir permohonan & pelacakan status tanpa login.
      *Belum ada:* halaman CMS untuk Bansos & PPID — API-nya sudah lengkap
      & teruji, antarmukanya menyusul.
- [x] **Fase 5 — Potensi & Ekonomi**: Potensi Desa (5 kategori + filter),
      Wisata (galeri foto, fasilitas, jam operasional, harga tiket berupa
      teks), dan Katalog UMKM (pencarian, filter kategori, paginasi, tautan
      hubungi penjual). Lengkap dengan CMS-nya.
      *Di luar cakupan atas permintaan:* keranjang belanja & pesan checkout
      WhatsApp — lihat DEVIASI A4.
- [x] **Fase 6 — Interaksi Publik & Peta**: Pengaduan Masyarakat (6 kategori,
      lampiran pada disk privat, nomor tiket acak, pelacakan tanpa login) dan
      Peta Desa berbasis Leaflet + OpenStreetMap dengan titik lokasi
      berkategori. Lengkap dengan CMS-nya.
- [x] **Fase 7 — QA, Keamanan & Deployment**: audit keamanan (header
      respons, kebocoran versi, perlindungan unggahan di `.gitignore`), audit
      performa (uji N+1 pada seluruh endpoint publik), CAPTCHA Cloudflare
      Turnstile pada 3 formulir publik, dan panduan deployment produksi.
