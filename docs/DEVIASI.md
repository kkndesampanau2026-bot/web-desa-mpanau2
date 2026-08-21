# Deviasi dari PRD v2.0 & Keputusan Teknis Fase 1

Dokumen ini mencatat setiap titik di mana implementasi berbeda dari PRD, beserta
alasannya — agar perbedaan menjadi keputusan sadar yang bisa ditinjau, bukan
penyimpangan diam-diam. **Butuh persetujuan pemilik produk.**

---

## A. Deviasi dari PRD

### A1. Versi runtime lebih baru dari yang disebut PRD

| Komponen | PRD | Terpasang | Alasan |
|---|---|---|---|
| PHP | 8.3+ | **8.2.12** | Versi yang tersedia di mesin pengembangan. Laravel 12 mensyaratkan PHP ≥ 8.2, jadi tetap didukung penuh. **Perlu diselaraskan ke 8.3+ sebelum produksi** agar sesuai PRD. |
| React | 18.3+ | **19.2** | Vite versi terbaru men-scaffold React 19. Masih memenuhi "18.3+", stabil, dan seluruh library yang PRD tetapkan mendukungnya (React Router, TanStack Query, Recharts, react-leaflet v5). |
| Vite | 5+ | **8.2** | Ikut dari scaffolding terbaru; memenuhi "5+". |
| MySQL | 8 | **8.4.3** | Sesuai PRD. |

**Risiko A1:** perbedaan minor PHP 8.2 vs 8.3 tidak berdampak pada fitur Fase 1,
tetapi menyamakan versi dev dan produksi mengurangi risiko kejutan saat deploy.

### A2. Database dari Laragon, bukan XAMPP

PRD dan asumsi awal mengarah ke XAMPP. Ternyata port 3306 di mesin ini dilayani
**MySQL 8.4.3 milik Laragon**, bukan MariaDB bawaan XAMPP. Klien XAMPP bahkan
gagal terhubung (`caching_sha2_password` tidak tersedia).

Ini justru **lebih sesuai PRD** yang menetapkan MySQL 8 — MariaDB bukan MySQL.
Klien CLI yang dipakai: `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`.

### A3. Redis belum dipakai pada Fase 1

PRD 7.1 menetapkan Redis untuk cache & queue. Ekstensi `redis` belum terpasang
di PHP mesin ini, sehingga Fase 1 memakai driver `database` untuk cache, queue,
dan session.

**Belum berdampak**, karena modul yang benar-benar membutuhkan Redis baru muncul
di fase berikutnya: snapshot agregasi kependudukan (Fase 3), impor CSV
asinkron (Fase 3), dan cache endpoint agregat (PRD 7.3).

**Status akhir (Fase 7):** seluruh fase diselesaikan tanpa Redis, memakai
driver `database`. Ini berfungsi penuh untuk satu server, namun ada satu
konsekuensi yang perlu diketahui sebelum produksi:

> **Rate limiting dihitung per-server.** Bila aplikasi kelak dijalankan di
> lebih dari satu proses atau server, batas 10 pencarian/menit pada Cek Bansos
> menjadi lebih longgar dari yang dimaksudkan — tiap server menghitung
> kuotanya sendiri. Untuk penyebaran satu server, tidak ada dampaknya.

Panduan pemasangan Redis di produksi ada di `DEPLOYMENT.md` bagian C.

### A4. Keranjang belanja & checkout WhatsApp tidak dibuat (Fase 5)

PRD 2.2 dan 6.12 menetapkan modul Belanja berupa katalog produk **ditambah**
keranjang sisi klien yang menghasilkan pesan checkout WhatsApp terstruktur.

**Atas permintaan pemilik produk, fitur keranjang tidak diimplementasikan.**
Modul Belanja berjalan sebagai katalog murni: pengunjung menelusuri produk,
lalu menghubungi penjual langsung lewat tautan WhatsApp yang hanya memuat
nama produk yang sedang dilihat.

Konsekuensi yang perlu diketahui:
- Pengunjung tidak dapat mengumpulkan beberapa produk dalam satu pesanan;
  tiap produk dihubungi terpisah. Untuk pembeli yang ingin memesan banyak
  produk dari penjual berbeda, ini memang sesuai kenyataannya — tiap UMKM
  dihubungi sendiri-sendiri.
- Tidak ada tabel keranjang, pesanan, maupun transaksi pada basis data.
  Data yang tidak dikumpulkan adalah data yang tidak perlu dijaga.
- Bila kelak keranjang dibutuhkan, ia dapat ditambahkan sepenuhnya di sisi
  klien tanpa mengubah skema maupun API — endpoint katalog sudah menyediakan
  seluruh data yang diperlukan.


---

## B. Perubahan pada lingkungan mesin (di luar folder proyek)

### B1. Ekstensi GD diaktifkan

`C:\xampp\php\php.ini` baris 931 diubah dari `;extension=gd` menjadi
`extension=gd`. Cadangan tersimpan sebagai `php.ini.bak-before-gd`.

**Alasan:** tanpa GD, Composer menolak `maatwebsite/excel` v3 dan mundur ke
v1.1 yang menarik `phpoffice/phpexcel` — paket usang dengan **20 advisory
keamanan**. GD juga dibutuhkan Spatie Media Library untuk memproses gambar
(resize/WebP, PRD 7.3). Setelah GD aktif, Laravel Excel 3.1.70 terpasang dan
`composer audit` bersih.

---

## C. Keputusan teknis yang tidak dirinci PRD

### C1. Blind index untuk NIK & No. KK

PRD 12.2 mewajibkan enkripsi at-rest untuk NIK/No. KK, tetapi PRD 6.6 juga
menuntut pencarian *exact match* (fitur Cek Bansos) dan PRD 6.3 menuntut
deduplikasi saat impor CSV. Keduanya tidak bisa dipenuhi sekaligus oleh kolom
terenkripsi biasa: enkripsi Laravel bersifat non-deterministik, sehingga nilai
sama menghasilkan ciphertext berbeda dan tidak dapat diindeks.

**Keputusan:** setiap kolom PII disimpan berpasangan —
- `nik` — terenkripsi (Laravel encrypted cast), untuk ditampilkan ke role berwenang
- `nik_hash` — HMAC-SHA256 deterministik berkunci `PII_HASH_KEY`, terindeks,
  untuk pencarian & deduplikasi

Kunci HMAC disimpan terpisah dari `APP_KEY` agar kebocoran satu kunci tidak
otomatis membuka keduanya. Implementasi kolom ini menyusul pada Fase 3.

### C1b. Impor CSV berjalan sinkron, belum lewat queue (Fase 3)

PRD 7.3 & 10.5 menyebut impor CSV sebaiknya diproses asinkron lewat queue.
Implementasi Fase 3 memprosesnya **sinkron**, dengan dua alasan:

1. Redis belum tersedia di lingkungan ini (lihat A3), sehingga queue worker
   belum dapat dijalankan.
2. Pemrosesan sinkron justru memberi operator ringkasan hasil per baris
   seketika — tanpa queue, tidak perlu membangun mekanisme notifikasi dan
   pengambilan hasil tertunda.

**Batas aman saat ini:** unggahan dibatasi 10 MB. Untuk desa berpenduduk
puluhan ribu, impor perlu dipindahkan ke queue agar tidak menabrak batas waktu
eksekusi PHP. Ini menjadi pekerjaan Fase 7 bersama pemasangan Redis.

### C1c. Data stunting tidak menyimpan identitas balita sama sekali (Fase 3)

PRD 6.5 menyebut data publik wajib agregat, namun membuka kemungkinan CMS
mencatat data by-name untuk keperluan intervensi Puskesmas.

Implementasi memilih **tidak menyediakan kolom identitas balita sama sekali**.
Pencatatan by-name adalah kebutuhan layanan kesehatan, bukan website profil
desa; membawanya masuk berarti menanggung risiko kebocoran data kesehatan anak
tanpa ada fitur yang memanfaatkannya. Bila desa membutuhkannya kelak, itu
sebaiknya menjadi modul terpisah dengan kendali akses sendiri.

### C2. Super Admin lewat `Gate::before`, bukan sinkronisasi permission

Role Super Admin sengaja tidak diberi permission eksplisit; aksesnya ditangani
`Gate::before` di `AppServiceProvider`. Dengan begitu permission baru yang lahir
pada fase berikutnya otomatis tercakup tanpa perlu menjalankan ulang seeder —
sumber kesalahan yang mudah terlewat.

### C3. Pemisahan `manage-*` dan `view-*-pii`

PRD 12.2 menyebut hanya Admin Utama yang boleh melihat/mengekspor data pribadi.
Agar prinsip minimalisasi data dapat ditegakkan di tingkat kode, hak *mengelola*
baris data dipisahkan dari hak *melihat NIK utuh*. Ini membuka kemungkinan
memberi operator akses kerja tanpa membuka data pribadi.

### C4. Pengujian memakai MySQL, bukan SQLite in-memory

Bawaan Laravel menguji di SQLite in-memory (cepat). Proyek ini memakai database
MySQL terpisah (`desa_mpanau_test`) karena skemanya bergantung pada kolom JSON,
indeks komposit, dan nantinya kolom hash terindeks — perilakunya berbeda
antar-engine, sehingga SQLite berisiko meloloskan galat skema yang baru muncul
di produksi.

Konsekuensi: suite lebih lambat (~5 detik) dan menuntut MySQL berjalan.

### C5b. `settings` memakai kolom bertipe, bukan key-value (Fase 2)

PRD 8.8 menyebut `settings` sebagai "key-value/struktur pengaturan umum".
Implementasi memilih **satu baris berkolom tegas**, bukan tabel key-value,
karena seluruh field sudah diketahui dari PRD 6.17 dan disunting sebagai satu
formulir. Kolom bertipe membuat validasi (mis. format kode wilayah, email)
dapat ditegakkan di tingkat basis data dan kode, sesuatu yang hilang bila
semua nilai disimpan sebagai string.

Dua daftar yang panjangnya bebas (nomor telepon penting & sosial media) tetap
dipisah ke tabelnya sendiri sesuai PRD.

### C5c. Kelayakan tayang berita dihitung lewat query, bukan job (Fase 2)

PRD 6.10 menyebut status `terjadwal`. Alih-alih menjalankan scheduled job yang
mengubah status menjadi `published` saat waktunya tiba, kelayakan tayang
dievaluasi langsung di query (`status IN (published, terjadwal) AND
tanggal_publish <= now()`).

Alasannya: artikel tidak akan pernah tersangkut di status `terjadwal` hanya
karena scheduler mati atau terlambat berjalan.

### C5d. Sanitasi HTML dilakukan saat menulis, bukan saat menampilkan (Fase 2)

Konten rich text (sambutan, sejarah, isi berita) dibersihkan dengan
HTMLPurifier **sebelum disimpan**. Dengan begitu HTML di basis data dijamin
bersih dan hanya ada satu titik kegagalan, alih-alih mengandalkan setiap
konsumen data (situs publik, aplikasi mobile di masa depan, ekspor) ingat
menyaringnya sendiri.

Paket tambahan: `mews/purifier` — tidak disebut PRD 18.2, tetapi PRD 6.10 &
12.2 mensyaratkan sanitasi HTML dan proteksi XSS.

### C5. Klien publik tidak mengirim kredensial

`frontend/public-app` sengaja **tidak** menyalakan `withCredentials`. Seluruh
layanan publik dirancang tanpa login (PRD 6.14–6.15), sehingga mengirim cookie
hanya memperluas permukaan serangan CSRF tanpa manfaat.

### C6. Hash terpisah untuk 4 digit terakhir NIK (Fase 4)

PRD 6.6 meminta pencarian publik memakai nama + 4 digit akhir NIK. Karena
kolom NIK terenkripsi non-deterministik, mencocokkan "4 digit terakhir"
menuntut server mendekripsi setiap baris pada setiap pencarian — mahal dan
justru memaparkan seluruh NIK ke memori aplikasi.

**Keputusan:** `bansos_recipients` menyimpan kolom `nik4_hash` tersendiri —
HMAC atas 4 digit terakhir saja. Pencocokan menjadi satu query terindeks,
dan tidak ada NIK yang perlu didekripsi untuk melayani pencarian publik.

Konsekuensi yang disadari: 4 digit hanya punya 10.000 kemungkinan, sehingga
kolom ini tidak boleh menjadi satu-satunya faktor. Karena itu pencocokan
selalu menuntut nama lengkap yang tepat DAN dibatasi lajunya.

### C7. Pesan & status HTTP dibuat seragam pada Cek Bansos (Fase 4)

Endpoint `/infografis/bansos/cek` selalu mengembalikan **200** dengan pesan
yang **identik** untuk semua kegagalan — tidak membedakan "nama tidak ada"
dari "digit salah", dan tidak memakai 404 saat data tidak ditemukan.

Alasannya: perbedaan sekecil apa pun antara kedua kasus dapat dipakai
mempersempit tebakan. Status HTTP yang berbeda membocorkan hal yang sama
seperti pesan yang berbeda, meski badan responsnya sudah dinetralkan.

### C8. Rate limiter Cek Bansos memakai named limiter (Fase 4)

Batas 10 permintaan/menit per IP (PRD 6.6) didefinisikan sebagai named
limiter `cek-bansos` di `AppServiceProvider`, bukan `throttle:10,1` di route,
sehingga angkanya dapat diperketat lewat env (`BANSOS_CHECK_RATE_LIMIT`)
tanpa menyentuh kode — misalnya saat log pemantauan menunjukkan upaya
enumerasi.

`GET /admin/bansos/pantau-pencarian` menyajikan IP dengan ≥30 pencarian dalam
7 hari terakhir sebagai sinyal awal scraping.

### C9. CAPTCHA — SELESAI pada Fase 7

> **Status: sudah diimplementasikan.** Catatan di bawah dipertahankan sebagai
> riwayat keputusan. Implementasinya memakai **Cloudflare Turnstile**
> (`App\Services\CaptchaVerifier`), terpasang pada tiga formulir publik:
> Cek Bansos, permohonan PPID, dan pengaduan masyarakat.
>
> Bersifat opsional lewat konfigurasi: bila `TURNSTILE_SECRET_KEY` kosong,
> verifikasi dilewati dan pertahanan lain tetap berjalan — sehingga desa dapat
> memasang situs lebih dulu lalu menambahkan kunci. **Wajib diisi sebelum
> produksi** (lihat `DEPLOYMENT.md` A5).
>
> Saat layanan Turnstile tak terjangkau, permintaan DITOLAK (gagal-tertutup),
> bukan diloloskan — kalau tidak, pertahanan ini dapat dilumpuhkan hanya
> dengan mengganggu koneksi ke Cloudflare.

#### Catatan asli (Fase 4)


PRD 6.6 & 12.2 menyebut CAPTCHA setelah beberapa kali gagal, sebagai
pelengkap rate limiting. **Belum diimplementasikan.**

Alasan penundaan: seluruh penyedia CAPTCHA arus utama (reCAPTCHA, hCaptcha,
Turnstile) menuntut kunci layanan pihak ketiga yang belum dimiliki desa, dan
memasang pilihan yang salah lebih sulit dibatalkan daripada menundanya.
Lapisan lain yang PRD sebutkan sudah aktif: rate limiting per IP, pencocokan
dua faktor, pesan netral, penyamaran nama, tiadanya endpoint listing massal,
dan log pemantauan.

**Tindakan sebelum produksi:** pilih penyedia CAPTCHA (Cloudflare Turnstile
gratis dan tidak melacak pengguna — cocok untuk situs pemerintah desa),
lalu pasang pada endpoint cek bansos dan formulir permohonan PPID.

### C10. Halaman CMS Fase 3–4 belum lengkap

API admin untuk APBDes, Stunting, IDM, SDGs, Bansos, dan PPID sudah lengkap,
tervalidasi, dan teruji (83+ test). Yang belum dibuat adalah halaman
antarmukanya di Admin App — kecuali Data Penduduk yang sudah ada.

Dampaknya: modul-modul tersebut untuk sementara hanya dapat diisi lewat API
atau seeder, belum lewat dashboard. Ini pekerjaan antarmuka, bukan celah
fungsional maupun keamanan.


### C11. Harga tiket wisata disimpan sebagai teks (Fase 5)

PRD 6.11 menyebut `harga_tiket` bersifat opsional dan "dapat Gratis".
Implementasi menyimpannya sebagai **string**, bukan kolom angka, karena
praktik di lapangan memakai nilai seperti "Gratis", "Sukarela", atau
"Rp5.000 (dewasa) / Rp3.000 (anak)" yang tidak terwakili oleh satu angka.

Konsekuensi: harga tiket tidak dapat diurutkan atau dijumlahkan. Itu memang
tidak dibutuhkan modul ini.

### C12. Nomor WhatsApp dinormalkan saat ditampilkan, bukan saat disimpan (Fase 5)

Warga menuliskan nomor dengan beragam format: `081234567890`,
`0812-3456-7890`, `+62 812 3456 7890`. Ketiganya harus menghasilkan tautan
wa.me yang sama.

Normalisasi ke format internasional (`62…`) dilakukan **saat menampilkan**
(`Product::whatsappInternasional()`), bukan saat menyimpan — supaya admin
tetap melihat nomor persis seperti yang ia masukkan dan dapat mencocokkannya
dengan catatan tertulis desa.


### C13. Lampiran pengaduan disimpan pada disk privat (Fase 6)

Lampiran dari publik TIDAK disimpan di `storage/app/public` yang tertaut ke
web root. Berkas hanya dapat diambil lewat endpoint admin yang memeriksa
permission, dan selalu dikirim sebagai `attachment` dengan header
`X-Content-Type-Options: nosniff`.

Alasannya berlapis: lampiran pengaduan kerap memuat foto rumah, wajah orang,
atau dokumen pribadi. Menaruhnya di direktori publik berarti siapa pun yang
menebak nama berkas dapat melihatnya, dan mesin pencari dapat mengindeksnya.

Pertahanan lain pada jalur unggah ini:
- Nama berkas di disk **diacak (UUID)**; nama dari pelapor hanya disimpan
  sebagai teks. Nama asli dapat memuat `../` atau ekstensi ganda
  (`foto.jpg.php`).
- MIME diperiksa dari **isi berkas** (aturan `mimetypes`, bukan `mimes`),
  karena ekstensi dan header Content-Type dikendalikan pengirim.
- Ekstensi di disk **ditentukan ulang server** dari MIME yang terdeteksi.
- Batas: 3 berkas, masing-masing 5 MB; hanya JPG, PNG, WebP, dan PDF.
- Formulir dibatasi 3 kiriman/menit per IP — lebih ketat daripada formulir
  publik lain karena inilah jalur termudah membanjiri penyimpanan server.

### C14. Ikon marker peta digambar sendiri, bukan bawaan Leaflet (Fase 6)

Ikon bawaan Leaflet memuat URL gambar relatif terhadap berkas CSS-nya, yang
rusak begitu aset di-bundle dan diberi hash oleh Vite — gejalanya marker
hilang tanpa pesan galat apa pun. Marker digambar sebagai SVG inline
(`L.divIcon`), sekaligus memungkinkan warna berbeda per kategori.

Peta juga selalu didampingi **daftar lokasi berbentuk teks**. Peta berbasis
kanvas tidak terbaca pembaca layar dan sulit dipakai lewat keyboard, sehingga
daftar itulah yang membuat informasi lokasi terjangkau semua pengunjung
(PRD 12.4).


### C15. Koreksi pemberian hak `respond-complaint` (ditemukan pada Fase 6)

Pada Fase 1, permission `respond-complaint` dikelompokkan bersama permission
PPID dalam grup `layanan`, dan grup itu diberikan utuh kepada Operator PPID.
Akibatnya **Operator PPID dapat menanggapi pengaduan** — termasuk membuka
nomor telepon pelapor — padahal PRD 1.3 menugaskan "kelola pengaduan masuk"
kepada Operator Konten, sementara Operator PPID hanya "Approval PPID".

Terungkap oleh test Fase 6 dan sudah diperbaiki: grup `layanan` dipecah
menjadi `ppid` dan `pengaduan`, lalu `pengaduan` diberikan kepada Operator
Konten saja.

**Tindakan bagi instalasi yang sudah berjalan:** jalankan ulang
`php artisan db:seed --class=RolePermissionSeeder` agar pemberian hak lama
tersinkron ulang.


### C16. Header keamanan respons (Fase 7)

Middleware `HeaderKeamanan` dipasang pada SELURUH respons, termasuk galat:

| Header | Alasan |
|---|---|
| `X-Powered-By` **dihapus** | Versi PHP hanya mempermudah penyerang mencocokkan kerentanan yang sudah diketahui publik. Dihapus lewat `header_remove()` karena PHP menyisipkannya di tingkat SAPI, di luar jangkauan koleksi header Laravel. |
| `X-Content-Type-Options: nosniff` | Mencegah peramban menebak tipe berkas — penting bagi unduhan lampiran pengaduan. |
| `X-Frame-Options: DENY` | API ini tidak pernah dimaksudkan tampil dalam frame. |
| `Referrer-Policy` | Menahan URL (yang memuat nomor tiket/registrasi) agar tidak bocor ke situs pihak ketiga. |
| `Permissions-Policy` | Mematikan fitur peramban yang tidak dibutuhkan API. |
| `Cache-Control: no-store` | Hanya pada jalur yang responsnya dapat memuat data pribadi (admin, pelacakan pengaduan/PPID, cek bansos). |

### C17. Perbaikan `.gitignore` untuk unggahan warga (Fase 7)

Ditemukan saat audit: `.gitignore` hanya mengabaikan
`backend/storage/app/public/`, sedangkan lampiran pengaduan disimpan pada
disk PRIVAT di `backend/storage/app/lampiran-pengaduan/`.

Akibatnya, begitu proyek dimasukkan ke git, **foto dan dokumen pribadi warga
yang dilampirkan pada pengaduan akan ikut ter-commit** — dan riwayat git sulit
dibersihkan setelah tersebar.

Sudah diperbaiki: seluruh isi `storage/app` diabaikan kecuali berkas
`.gitignore` bawaan Laravel. Diverifikasi pada repo git sementara.

### C18. Uji N+1 sebagai bagian dari suite (Fase 7)

`tests/Feature/Fase7/PerformaTest.php` menghitung JUMLAH QUERY, bukan waktu
eksekusi yang bergantung mesin. Caranya: memanggil endpoint dengan data
sedikit lalu dengan data lebih banyak, dan menuntut jumlah query **tetap
sama**.

Masalah N+1 tidak terlihat pada data contoh yang sedikit, tetapi meledak saat
desa mengisi ratusan record — persis keadaan yang tidak dapat diuji manual
sebelum terlambat.

Satu jebakan yang sempat menyesatkan: middleware pencatat kunjungan menulis
log pada kunjungan PERTAMA sebuah sesi lalu berhenti, sehingga selisih satu
query dari efek samping itu terbaca seolah-olah N+1. Helper `ukurQuery()`
menjalankan permintaan pemanasan lebih dulu untuk menetralkannya.

Hasil audit: **tidak ada N+1** pada endpoint berita, produk, POI, galeri,
maupun infografis bansos.


---

## D. Catatan yang perlu diverifikasi ke pihak desa

### D1. Daftar dusun masih placeholder

`VillageSeeder` mengisi "Dusun 1/2/3" karena PRD tidak memuat daftar dusun resmi
Desa Mpanau. Nama sebenarnya **wajib diverifikasi** sebelum modul Kependudukan
(Fase 3) diisi data, karena `dusuns` menjadi acuan FK bagi `residents`,
`stunting_records`, dan `points_of_interest`.

### D2. Kode wilayah

Dipakai `72.10.01.2013` sesuai contoh pada PRD 3.2. Perlu dikonfirmasi bahwa ini
memang kode wilayah resmi Desa Mpanau, bukan sekadar contoh format.

### D3. Persetujuan pelaku UMKM atas penayangan nomor kontak

Modul Belanja menayangkan nama dan nomor WhatsApp pelaku UMKM ke publik.
Meski ini kontak usaha (bukan data pribadi warga seperti pada modul
Kependudukan), **persetujuan pemilik usaha tetap harus diperoleh** sebelum
nomornya dipublikasikan.

Halaman CMS sudah memuat pengingat ini pada formulir input. Desa sebaiknya
menyimpan bukti persetujuan tertulis dari tiap pelaku UMKM.

### D4. Koordinat titik lokasi wajib diambil ulang

`PetaDemoSeeder` mengisi koordinat **perkiraan** di sekitar wilayah Sigi
Biromaru semata agar peta dapat diuji tampilannya. Titik sesungguhnya wajib
diambil admin desa dari lokasi fisik yang benar.

Peta yang menunjukkan puskesmas atau kantor desa di tempat yang keliru lebih
berbahaya daripada peta yang kosong — warga bisa menuju lokasi yang salah saat
keadaan mendesak.

### D5. Batas ukuran unggahan harus diselaraskan dengan PHP

`MediaService` membatasi gambar 5 MB dan dokumen 10 MB. Batas ini hanya berlaku
bila `php.ini` mengizinkannya:

```ini
upload_max_filesize = 12M
post_max_size = 64M      ; galeri menerima 10 foto sekaligus
max_file_uploads = 20
```

Bila `post_max_size` lebih kecil, PHP membuang seluruh body request **sebelum**
Laravel sempat memvalidasinya. Gejalanya menyesatkan: form seakan terkirim
kosong dan admin melihat pesan "wajib diisi" pada kolom yang jelas-jelas sudah
ia isi, bukan pesan bahwa berkasnya terlalu besar.

### D6. Konversi gambar bergantung pada ekstensi GD

Semua gambar unggahan diperkecil ke lebar maksimum 1600 px lalu dikonversi ke
WebP. Bila `ext-gd` tidak aktif, unggahan **tetap berhasil** — berkas aslinya
disimpan apa adanya dan kegagalan konversi dicatat sebagai peringatan di log.

Ini pilihan sadar: kehilangan penghematan ukuran jauh lebih ringan akibatnya
daripada kehilangan foto kegiatan desa yang tidak dapat diunggah ulang.
Periksa `storage/logs/laravel.log` untuk memastikan konversi berjalan di server
produksi.
