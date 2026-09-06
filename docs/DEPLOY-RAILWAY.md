# Deploy ke Railway

Catatan operasional penyebaran situs ke Railway. Berkas ini menjelaskan *mengapa*
setiap potong konfigurasi berbentuk seperti sekarang; untuk daftar perintah
pengembangan sehari-hari lihat `README.md`.

## Ringkasan

| | |
|---|---|
| Project Railway | `attractive-freedom` (nama bawaan Railway, boleh diganti lewat dasbor) |
| Lingkungan | `production` |
| Layanan | `mysql` (MySQL 8.0) dan `web-desa-mpanau2` (aplikasi) |
| Alamat | https://web-desa-mpanau2-production.up.railway.app |
| Sumber | GitHub `kkndesampanau2026-bot/web-desa-mpanau2`, branch `main` |

Setiap push ke `main` memicu build dan deploy otomatis.

## Mengapa Dockerfile, bukan Nixpacks

`nixpacks.toml` yang sebelumnya ada di repo tidak akan pernah berhasil mem-build
proyek ini:

```toml
[phases.setup]
nixPkgs = ["php82Extensions.gd", "php82Extensions.exif"]
```

Nixpacks *mengganti* daftar `nixPkgs` milik penyedia PHP, bukan menambah, kecuali
daftar barunya diawali token `"..."`. Tanpa token itu php, node, dan composer
hilang dari tahap setup. Alih-alih menambal, seluruh proses build dipindahkan ke
`Dockerfile` supaya versi PHP, daftar ekstensi, dan konfigurasi nginx menjadi
eksplisit dan sama persis antara mesin lokal dan Railway.

## Bentuk kontainer

Satu kontainer, tiga proses, dikelola supervisor (`docker/supervisord.conf`):

- **nginx** — melayani berkas statis (`public/build`, `public/storage`) dan
  meneruskan sisanya ke php-fpm.
- **php-fpm** — menjalankan Laravel.
- **`php artisan queue:work`** — satu-satunya pekerjaan terantre di aplikasi ini
  adalah notifikasi Telegram Surat Pengantar
  (`app/Jobs/KirimNotifikasiSurat.php`). Memisahkannya menjadi layanan Railway
  tersendiri berarti membayar satu layanan penuh untuk beban yang nyaris nol.

Build memakai dua tahap: tahap `aset` (Node 22) menjalankan `npm run build`,
tahap `app` (PHP 8.3 FPM Alpine) memasang dependensi Composer dan menyalin hasil
build Vite. `node_modules` dan `vendor` tidak pernah ikut ke citra akhir.

## Hal-hal yang mudah salah

Empat jebakan yang sudah ditangani `docker/entrypoint.sh` — jangan dihapus tanpa
memahami akibatnya:

1. **`route:cache` TIDAK dijalankan.** `routes/api.php` memuat dua route yang
   aksinya berupa closure (`/api/v1/health` dan `/api/v1/admin/ping`). Laravel
   menolak men-serialisasi closure, jadi perintahnya pasti gagal dan menggagalkan
   seluruh boot. `config:cache` dan `view:cache` tetap dijalankan.
2. **Volume membayangi isi `storage/app`.** Volume Railway dipasang pada
   `/var/www/html/storage/app` dan datang dalam keadaan kosong, menutupi
   `public/` dan `private/` bawaan citra. Entrypoint membuat ulang kedua
   direktori itu setiap boot, sebelum `storage:link`.
3. **`VITE_API_URL` wajib terdefinisi saat build.** Dua layar CMS
   (`Pages/Admin/Penduduk.tsx`, `Pages/Admin/Pengaduan.tsx`) menyusun tautan
   unduhan dari `import.meta.env.VITE_API_URL ?? 'http://localhost:8000'`. Bila
   variabelnya tidak ada sama sekali, tautan unduhan di produksi menunjuk ke
   localhost. Dockerfile menuliskannya sebagai string kosong sehingga tautan
   menjadi relatif terhadap origin dan benar di domain mana pun.
4. **`SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN=null`.** Railway selalu
   HTTPS. `SESSION_DOMAIN` yang diisi akan membuat cookie sesi tidak terkirim dan
   setiap pengiriman formulir gagal dengan 419 — alasan yang sama seperti yang
   sudah dicatat di `.env.example`.

## Variabel lingkungan

Basis data dirujuk lewat variabel referensi Railway, bukan disalin:
`DB_HOST=${{mysql.MYSQLHOST}}` dan seterusnya. Bila kata sandi MySQL diganti,
layanan web ikut memperbarui nilainya sendiri.

Yang wajib ada dan tidak boleh bocor:

- `APP_KEY` — enkripsi sesi dan nilai NIK/No. KK.
- `PII_HASH_KEY` — kunci HMAC blind index. **Terpisah dari `APP_KEY` dengan
  sengaja** (lihat `docs/DEVIASI.md` §C1). Menggantinya membuat seluruh hash yang
  ada tidak cocok lagi dan pencarian NIK berhenti berfungsi sampai data
  di-index ulang.
- `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`.

Entrypoint sengaja berhenti dengan pesan galat bila `APP_KEY` atau
`PII_HASH_KEY` kosong, alih-alih menyala dengan data pribadi yang tidak
terlindungi.

### Variabel khusus penyebaran

| Variabel | Fungsi |
|---|---|
| `JALANKAN_SEEDER` | Bila `true`, `php artisan db:seed --force` dijalankan tiap boot. **Setel ke `false` setelah seeding pertama berhasil** agar konten yang diedit lewat CMS tidak tertimpa nilai seeder. |
| `AKUN_DEMO_NONAKTIF` | Daftar email dipisah koma yang `status_aktif`-nya dimatikan setiap boot. Dijalankan *setelah* seeder, sehingga akun demo tetap mati walau seeder terlanjur berjalan ulang. |
| `SEED_ADMIN_PASSWORD` | Kata sandi akun operator yang dibuat `AdminUserSeeder`. |

## Data awal

`APP_ENV` disetel `staging`, bukan `production`. Ini disengaja: seluruh seeder
konten contoh (`KontenDemoSeeder`, `DataTransparansiDemoSeeder`, dan kawan-kawan)
serta `AdminUserSeeder` memeriksa `app()->isProduction()` dan melewati dirinya
sendiri di produksi. Dengan `staging`, situs tampil berisi sejak menit pertama
dan akun operator ikut terbuat.

Konsekuensinya: **angka APBDes, data bansos, dan berita yang tampil sekarang
adalah data contoh, bukan data resmi Desa Mpanau.** Ganti lewat CMS sebelum
alamat situs disebarkan ke warga, lalu setel `APP_ENV=production` dan
`JALANKAN_SEEDER=false`.

## Yang belum aktif

- **CAPTCHA (Cloudflare Turnstile).** `TURNSTILE_SITE_KEY` dan
  `TURNSTILE_SECRET_KEY` dibiarkan kosong, sehingga verifikasi dilewati dan
  formulir publik hanya terlindungi rate limiting. `CaptchaVerifier` bersifat
  *fail-closed*: begitu `TURNSTILE_SECRET_KEY` diisi, formulir cek bansos,
  permohonan PPID, dan pengaduan akan menolak (422) setiap kiriman tanpa token
  Turnstile yang sah — widget di sisi frontend harus terpasang lebih dulu.
- **Notifikasi Telegram Surat Pengantar.** `TELEGRAM_BOT_TOKEN` kosong;
  pengajuan surat tetap tersimpan dan tercatat, hanya notifikasi ke Ketua RT
  yang tidak terkirim. Setelah token diisi, jalankan
  `php artisan telegram:webhook` (lihat `PasangWebhookTelegram`).
- **Surel.** `MAIL_MAILER=log`; tidak ada surel keluar.

## Memeriksa keadaan

```bash
railway logs                     # log runtime
railway logs --build             # log build
railway run php artisan tinker   # shell aplikasi dengan variabel produksi
```

Endpoint kesehatan ada di `/up` (didaftarkan `bootstrap/app.php`) dan dipakai
Railway sebagai healthcheck deploy.
