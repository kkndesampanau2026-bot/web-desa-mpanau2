# Panduan Deployment & Daftar Periksa Produksi

Dokumen ini menyiapkan Website Profil Desa Mpanau untuk dijalankan di server
sungguhan. Bagian A **wajib** diselesaikan sebelum situs dibuka ke publik —
sistem ini menyimpan NIK, nomor telepon, dan pengaduan warga.

---

## A. Wajib sebelum go-live

### A1. Kunci & kredensial

```bash
cd backend
php artisan key:generate     # APP_KEY — mengenkripsi NIK & No. KK
php artisan pii:key          # PII_HASH_KEY — blind index pencarian NIK
```

- [ ] `APP_KEY` dan `PII_HASH_KEY` dibuat **baru di server produksi**, bukan
      disalin dari lingkungan pengembangan.
- [ ] Keduanya dicadangkan di tempat aman. **Kehilangan `APP_KEY` berarti
      seluruh NIK tersimpan tidak dapat didekripsi lagi** — tidak ada jalan
      pemulihan.
- [ ] `DB_PASSWORD` diisi; akun database bukan `root`.
- [ ] Berkas `.env` tidak pernah masuk ke git (sudah diatur `.gitignore`).

### A2. Mode produksi

```env
APP_ENV=production
APP_DEBUG=false
```

- [ ] `APP_DEBUG=false`. Bila `true`, halaman galat akan menampilkan isi
      `.env` — termasuk kunci enkripsi data warga — kepada siapa pun yang
      memicu error.
- [ ] `APP_URL` diisi domain sesungguhnya (dipakai menyusun URL berkas).

### A3. Domain frontend & CORS

```env
SANCTUM_STATEFUL_DOMAINS=desa-mpanau.id,admin.desa-mpanau.id
SESSION_DOMAIN=.desa-mpanau.id
FRONTEND_PUBLIC_URL=https://desa-mpanau.id
FRONTEND_ADMIN_URL=https://admin.desa-mpanau.id
```

- [ ] Domain diisi tanpa wildcard. `config/cors.php` menyalakan
      `supports_credentials`, dan browser menolak kombinasi wildcard +
      credentials — situs tidak akan bisa login bila diisi `*`.

### A4. HTTPS

- [ ] Sertifikat TLS terpasang (Let's Encrypt cukup dan gratis).
- [ ] HTTP dialihkan ke HTTPS.
- [ ] Cookie sesi ditandai secure:

```env
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

Tanpa HTTPS, kredensial admin dan isi pengaduan warga terkirim sebagai teks
terbaca di jaringan.

### A5. CAPTCHA

- [ ] `TURNSTILE_SITE_KEY` & `TURNSTILE_SECRET_KEY` diisi.

Dapatkan gratis di **dash.cloudflare.com → Turnstile → Add site**. Bila
dikosongkan, formulir publik tetap berjalan dengan rate limiting saja —
memadai untuk uji coba, tidak memadai untuk produksi.

### A6. Akun awal

- [ ] Seeder konten demo **tidak** dijalankan di produksi (sudah otomatis
      dilewati saat `APP_ENV=production`).
- [ ] Akun operator dibuat dengan kata sandi yang kuat, bukan `password`
      bawaan pengembangan.

```bash
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force   # role & permission saja
```

Akun pertama dibuat manual lewat `php artisan tinker`, lalu operator lain
ditambahkan dari dashboard.

### A7. Izin berkas

- [ ] `storage/` dan `bootstrap/cache/` dapat ditulis oleh pengguna web server.
- [ ] `storage/app/lampiran-pengaduan/` **tidak** dapat diakses langsung dari
      web. Lampiran pengaduan memuat foto rumah dan dokumen pribadi warga;
      hanya endpoint admin berpagar izin yang boleh menyajikannya.
- [ ] `php artisan storage:link` dijalankan (untuk berkas publik: logo, foto
      berita, galeri).
- [ ] `upload_max_filesize`, `post_max_size`, dan `max_file_uploads` pada
      `php.ini` disetel sesuai DEVIASI D5 — batas PHP yang lebih kecil membuat
      form unggahan gagal dengan pesan yang menyesatkan.
- [ ] Ekstensi `gd` aktif (`php -m | grep gd`), agar gambar unggahan
      diperkecil dan dikonversi ke WebP alih-alih disimpan mentah (DEVIASI D6).

Konfigurasi Nginx yang menutup akses langsung ke storage privat:

```nginx
location ~ ^/storage/(app|framework|logs) {
    deny all;
    return 404;
}
```

---

## B. Optimasi produksi

```bash
cd backend
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

> **Catatan:** setelah `config:cache`, seluruh pemanggilan `env()` di luar
> berkas config akan mengembalikan null. Proyek ini sudah membaca konfigurasi
> lewat `config()`, jadi aman — tetapi ingat menjalankan `config:clear`
> sebelum mengubah `.env`.

Frontend:

```bash
cd frontend/public-app && npm ci && npm run build
cd ../admin-app && npm ci && npm run build
```

Hasil build ada di `dist/`. Sajikan sebagai berkas statis, dengan fallback
SPA ke `index.html` (kedua aplikasi memakai routing sisi klien).

---

## C. Redis (disarankan)

Belum dipakai pada pengembangan (lihat `DEVIASI.md` A3). Di produksi, Redis
memberi tiga manfaat nyata:

1. **Cache** endpoint agregat — target respons <500ms PRD 12.1.
2. **Rate limiting** yang tetap konsisten bila aplikasi berjalan di lebih dari
   satu proses/server. Dengan driver `database`, batas Cek Bansos dihitung
   per-server dan menjadi lebih longgar dari yang dimaksudkan.
3. **Queue** untuk impor CSV kependudukan berukuran besar.

```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
```

Jalankan worker sebagai layanan yang otomatis hidup kembali:

```bash
php artisan queue:work --tries=3 --timeout=300
```

---

## D. Penjadwalan (cron)

Snapshot agregat kependudukan dan ringkasan kunjungan harian perlu dijalankan
berkala.

```cron
* * * * * cd /path/ke/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## E. Pencadangan (PRD 12.6)

- [ ] Backup basis data **otomatis harian**, retensi minimal 30 hari.
- [ ] Backup berkas media **mingguan** (`storage/app`).
- [ ] Backup disimpan di lokasi berbeda dari server aplikasi.
- [ ] **Prosedur restore diuji**, bukan hanya didokumentasikan. Backup yang
      belum pernah dipulihkan belum tentu backup.

Perhatian: basis data memuat NIK terenkripsi. Backup-nya sama sensitifnya
dengan basis data aslinya dan harus disimpan terenkripsi pula. Backup tanpa
`APP_KEY` tidak dapat dipulihkan isinya — simpan kunci terpisah, tetapi jangan
sampai hilang.

---

## F. Pemantauan setelah go-live

- [ ] Log aplikasi dipantau (`storage/logs/laravel.log` atau layanan
      log terpusat).
- [ ] **Panel Pantau Pencarian** pada menu Bantuan Sosial diperiksa berkala.
      Satu IP dengan puluhan pencarian gagal adalah pola enumerasi data
      penerima bantuan, bukan warga yang mengecek statusnya sendiri.
- [ ] **Log Aktivitas** diperiksa berkala untuk memastikan akses ke data
      pribadi (NIK, kontak pelapor) memang dilakukan petugas berwenang.

---

## G. Sebelum menyerahkan ke desa

- [ ] Nama dusun sebenarnya sudah diisi (menggantikan "Dusun 1/2/3").
- [ ] Kode wilayah dikonfirmasi ke dokumen resmi.
- [ ] Koordinat titik lokasi diambil dari lokasi fisik yang benar.
- [ ] Persetujuan tertulis pelaku UMKM atas penayangan nomor kontaknya.
- [ ] Operator desa dilatih, terutama alur impor CSV kependudukan.
- [ ] Desa memahami kewajibannya sebagai pengendali data pribadi menurut
      UU No. 27/2022 — termasuk menanggapi permintaan koreksi/penghapusan
      data dari warga.

---

## H. Verifikasi setelah deploy

```bash
# Seluruh test harus lulus di server
cd backend && php artisan test

# Tidak ada paket dengan kerentanan diketahui
composer audit
cd ../frontend/public-app && npm audit --omit=dev
```

Periksa juga dengan cepat lewat peramban:

- [ ] Halaman publik terbuka tanpa galat konsol.
- [ ] Login admin berhasil; menu tampil sesuai role.
- [ ] Formulir pengaduan mengembalikan nomor tiket.
- [ ] Lampiran pengaduan **tidak** dapat dibuka lewat URL langsung.
- [ ] Cek Bansos menolak permintaan ke-11 dalam satu menit (429).
- [ ] Header respons tidak memuat `X-Powered-By`.
