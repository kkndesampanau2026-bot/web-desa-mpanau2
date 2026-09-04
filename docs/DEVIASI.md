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


### A5. Pengaduan & Cek Penerima Bansos ditarik dari situs publik (4 September 2026)

PRD 6.6 menetapkan fitur **Cek Penerima Bansos** dan PRD 6.15 menetapkan
**Pengaduan Masyarakat** sebagai layanan warga di situs publik. **Atas
permintaan pemilik produk, keduanya tidak lagi ditawarkan dari halaman mana
pun.** Yang dihapus:

- Tombol "Kirim Pengaduan" pada hero Beranda, serta kartu "Pengaduan
  Masyarakat" dan "Lacak Pengaduan" di Layanan Mandiri.
- Formulir **Cek Penerima** pada `/infografis/bansos` berikut route
  `POST /infografis/bansos/cek` dan method `InfografisController::cekPenerima`.
  Halaman itu kini murni agregat: total penerima dan jumlah penerima per jenis
  bantuan, tanpa jalur apa pun menuju data per orang.
- Kartu "Cek Penerima Bansos" di Layanan Mandiri.

Yang **tidak** dihapus, beserta alasannya:

- Modul Pengaduan sisi CMS (`/admin/pengaduan`), model `Complaint`, tabelnya,
  lampiran, dan lonceng notifikasi operator — pengaduan yang sudah masuk tetap
  harus dapat ditindaklanjuti dan diarsipkan.
- **Tombol mengambang "Aduan Warga" di sudut kanan bawah** beserta popup
  formulirnya (`Components/AduanWarga.tsx`) — atas permintaan pemilik produk,
  inilah satu-satunya pintu masuk pengaduan yang tetap ditawarkan, dan ia hadir
  di seluruh halaman publik termasuk Beranda. Karena itu prop bersama
  `kategori_pengaduan` pada `HandleInertiaRequests` juga tetap dikirim.
- Route publik `/pengaduan` dan `/pengaduan/lacak`. Tidak ada tautan menu
  menuju keduanya, tetapi popup di atas mengirim ke sana (server lalu
  mengalihkan ke halaman tanda terima berisi nomor tiket), dan nomor tiket yang
  terlanjur dipegang warga masih dapat dilacak lewat alamat yang sudah mereka
  simpan.
- Endpoint API lama `POST /api/v1/infografis/bansos/cek` beserta
  `BansosSearchService`, log pencariannya, dan layar pemantauan anomali di CMS.
  Seluruh lapisan `routes/api.php` memang dijadwalkan dihapus pada "Fase 5"
  migrasi monolit (lihat `docs/MIGRASI-MONOLIT.md`); mencabutnya sendirian di
  sini akan mematikan layar pemantauan yang masih terpasang dan membuang
  rangkaian uji privasi `tests/Feature/Fase4/CekBansosTest.php` yang menjaga
  aturan PRD 6.6. **Perlu keputusan pemilik produk:** bila fitur ini dianggap
  ditutup seluruhnya, endpoint tersebut ikut dihapus bersama Fase 5.


### A6. Tata letak situs publik disatukan pada satu kisi (4 September 2026)

PRD 3.1–3.2 dan desain Figma menetapkan beberapa halaman memakai kepala
halaman **terpusat** di atas latar krem (Profil, Infografis, Informasi PPID),
sementara sisanya memakai bilah navy rata kiri. Dalam pemakaian, perbedaan itu
membuat situs terasa seperti kumpulan halaman yang dikerjakan terpisah: tepi
kiri judul berpindah-pindah antar halaman, dan pengunjung kehilangan pegangan
tiap kali bernavigasi. **Atas permintaan pemilik produk, seluruh halaman
publik kini memakai satu pola.**

Yang berubah:

- **Satu kisi.** `GAYA_WADAH` (`Components/ui`) — dipakai bilah atas,
  navigasi, kepala halaman, isi, dan footer. Sebelumnya ada **tujuh** lebar
  berbeda (`max-w-2xl` sampai `max-w-situs`) dengan gutter `px-6` tetap di
  semua ukuran layar.

  Lebarnya MENGIKUTI layar dengan margin tepi tipis (`px-5 sm:px-6 lg:px-10
  xl:px-16 2xl:px-22`), dibatasi 120rem/1920px — bukan berhenti pada satu
  angka lalu memusat. Ini permintaan pemilik produk, diukur dari situs desa
  yang dijadikan acuan: pada monitor 1902px isinya bermula 90px dari tepi
  (±4,7% lebar layar), sedangkan container 1280px yang memusat menyisakan
  320px kosong di kiri dan kanan. Kisi kartu karena itu mendapat kolom
  keempat mulai 1536px (`2xl:grid-cols-4`) — dengan tiga kolom, kartu
  memanjang sampai ±560px dan gambar sampulnya setinggi 350px untuk berita
  berisi dua baris teks.
- **Kolom sempit dipusatkan, berikut judulnya.** Halaman formulir dan artikel
  tetap dibatasi ±736px demi panjang baris. Sempat dicoba rata kiri agar
  tepinya segaris dengan logo, tetapi pada monitor lebar hasilnya timpang —
  formulir menempel ke kiri dan menyisakan ±1000px kosong di kanan. Kuncinya:
  `KepalaHalaman` menerima prop `lebar` yang HARUS sama dengan `IsiHalaman`
  di halaman itu, sehingga judul ikut terpusat dan keduanya berbagi satu tepi
  kiri. Tanpa itu, judul menempel kiri sementara isinya di tengah.
- **`KepalaHalaman` dipakai SETIAP halaman publik**, termasuk yang dulu
  memakai judul terpusat (Profil, Infografis, Informasi PPID) atau `<h1>`
  polos (Galeri, Listing). Ia mendapat dua slot baru: `kembali` (tautan ke
  daftar induk) dan `meta` (tanggal, penulis, jumlah dibaca), yang menggantikan
  kepala halaman buatan sendiri di halaman detail berita & album.
- **Satu bentuk sub-navigasi modul.** `BilahTab` kini dipakai Infografis saja;
  PPID sempat memakainya juga, lalu bilah tabnya dihapus atas permintaan
  pemilik produk — halaman PPID dinavigasikan lewat menu utama dan kartu
  kategori di `/ppid`. Sebelum disatukan, keduanya berbeda bentuk (pil bulat
  terpusat vs tab bergaris bawah lengket). Atas permintaan pemilik produk, bentuk yang dipakai keduanya
  adalah **pil bulat terpusat** milik Infografis — setelah sempat disatukan sebagai
  tab bergaris bawah rata kiri. Deretan pil itu bagian dari isi halaman dan
  ikut menggulir — bukan bilah lengket berlatar sendiri di bawah header, yang
  sempat dicoba dan menghasilkan dua batang navigasi bertumpuk saat halaman
  digulir. Yang tetap dari penyatuan: satu komponen untuk kedua modul dan
  gulir mendatar pada layar sempit. Pemusatannya memakai `w-max mx-auto` pada
  lapisan dalam, bukan `justify-center` pada kotak yang menggulir — margin
  auto hanya membagi ruang berlebih sehingga menjadi nol saat deretan pil
  melampaui lebar layar, sedangkan `justify-center` akan mendorong luapannya
  ke kedua sisi dan membuat pil pertama tidak dapat dicapai betapapun
  digulir. Bilah menempel di bawah header lewat `--tinggi-header` di `app.css`
  — menggantikan `top-17` yang ditulis lepas dan sudah tidak sesuai tinggi
  header sebenarnya, sehingga bilah tab tertimbun separuh saat digulir.
- **Jarak menuju footer.** Kerangka publik tidak lagi dipaksa setinggi layar
  dengan footer didorong ke dasar; footer kini mengikuti konten, dan latar
  `html` disetel senada footer sehingga ruang sisa di bawahnya terbaca sebagai
  footer yang memanjang — bukan jurang krem setinggi ratusan piksel pada
  halaman berisi sedikit konten.
- **Responsif per-breakpoint, bukan sekadar mengecil.** Menu mendatar baru
  muncul di `xl` (delapan itemnya berdesakan di `lg`); di bawah itu panel dua
  kolom. Gambar kartu memakai rasio (`aspect-[16/10]`, `aspect-[4/3]`) alih-alih
  tinggi tetap `h-44`, tinggi peta mengikuti layar, dan bilah atas menyembunyikan
  nama wilayah di bawah `sm`.
- **Pengurangan hiasan.** Bulatan emas kabur di hero, lencana berbentuk pil,
  ubin ikon berwarna pada setiap kartu, sudut `rounded-2xl`, dan bayangan yang
  mengembang saat disentuh — semuanya dikurangi menjadi garis tepi tipis,
  sudut `rounded-xl`, dan ikon polos.

Satu cacat lama ikut ditemukan dan diperbaiki dalam proses ini: token grafik
(`Components/viz/tokens.css`) membawa blok `@media (prefers-color-scheme: dark)`
yang menukar warna deret menjadi versi terangnya begitu SISTEM OPERASI
pengunjung disetel gelap — padahal situs ini tidak punya tema gelap dan kartu
grafiknya tetap putih. Akibatnya biru muda di atas putih hanya berkontras
±2,2:1 bagi pengunjung tersebut. Blok itu dihapus; penggantinya, mode kontras
tinggi kini ikut menggelapkan warna deret.

### A7. Banner beranda dapat diganti dari CMS (4 September 2026)

PRD 6.17 mendaftar isi Pengaturan Umum tanpa menyebut gambar banner; hero
beranda semula tidak ada. Atas permintaan pemilik produk, beranda kini memakai
foto latar setinggi satu layar, dan **fotonya dapat diganti sendiri oleh
perangkat desa** lewat Pengaturan Umum — bukan berkas statis yang menuntut
akses server.

- Kolom baru `settings.banner` menyimpan path relatif pada disk `public`,
  sepola `logo`. Unggahannya melewati `MediaService` yang sudah ada: nama
  diacak, MIME disidik dari isi berkas, dikonversi ke WebP.
- `MediaService::simpanGambar()` menerima parameter lebar maksimum opsional.
  Banner dipotong pada 1920px (`LEBAR_MAKS_BANNER`), bukan 1600px seperti
  gambar lain: ia membentang sampai tepi jendela, sehingga pada monitor
  1920px batas lama membuatnya diregangkan dan buram — tepat pada elemen yang
  pertama dilihat pengunjung.
- Bila kolomnya kosong (keadaan setiap desa pada hari pertama), Beranda
  memakai foto bawaan di `public/gambar/` yang disajikan lewat `<picture>`
  tiga ukuran. Berkas asli PNG 2,3 MB tidak pernah disajikan apa adanya;
  varian WebP 800px hanya 49 KB.
- Lapisan navy 80% + gradien di atas foto bukan hiasan: teks putih dan label
  emas kecil harus tetap terbaca di atas foto APA PUN yang diunggah admin.
  Pada bagian paling terang sekalipun, teks putih berkontras ±9:1 dan label
  emas-muda ±5,8:1. Karena itu pula label kecil di hero memakai `gold-light`,
  bukan `gold` — emas tua hanya mencapai ±4,2:1 di atas latar bercampur foto.

### A8. Perbaikan: penyimpanan Pengaturan Umum tidak pernah berhasil

Ditemukan saat menguji fitur banner di atas, dan **sudah ada sejak commit
pertama** — bukan akibat perubahan tampilan.

`SettingController::update()` membungkus penyimpanannya dalam
`DB::transaction(function () use ($data, $villageId, &$setting) { ... })`,
tetapi di dalam blok itu memanggil `$request->hasFile('logo')`. Closure PHP
tidak mewarisi lingkup pemanggilnya, jadi `$request` bernilai null di sana —
dan karena baris tersebut dilalui pada SETIAP penyimpanan (bukan hanya ketika
ada berkas), **seluruh penyimpanan Pengaturan Umum berakhir galat 500**. Tidak
ada uji yang menyentuh endpoint ini sebelumnya, sehingga kegagalannya tidak
pernah terlihat.

Perbaikannya satu kata: `$request` ikut ditangkap pada klausa `use`. Rangkaian
uji `tests/Feature/Publik/BannerBerandaTest.php` kini menjaga jalur itu —
termasuk perkara yang paling mudah terlewat, yaitu menyimpan formulir tanpa
memilih berkas tidak boleh mengosongkan logo/banner yang sudah ada.

### A9. CRUD dilengkapi pada seluruh modul CMS (4 September 2026)

PRD Bagian 5 menyebut tiap modul CMS "dikelola" tanpa merinci operasinya.
Dalam implementasinya, sebagian modul hanya memiliki **tambah + hapus**:
mengoreksi satu salah ketik pada judul dasar hukum PPID menuntut operator
menghapus barisnya lalu mengunggah ulang PDF-nya — dan tautan lama yang sudah
telanjur dibagikan warga ikut mati. Atas permintaan pemilik produk, operasi
ubah/hapus dilengkapi.

Endpoint yang ditambahkan:

| Endpoint | Alasan sebelumnya tidak ada |
|---|---|
| `PUT /admin/ppid/dasar-hukum/{id}` | hanya tambah & hapus |
| `PUT /admin/ppid/informasi/{id}` | hanya tambah & hapus |
| `PUT /admin/bansos/jenis/{id}` | hanya tambah & hapus |
| `PUT`, `DELETE /admin/apbdes/kategori/{id}` | kategori hanya dapat ditambah |
| `DELETE /admin/apbdes/tahun/{id}` | tahun anggaran tidak dapat dihapus |
| `DELETE /admin/idm/{id}` | skor IDM tidak dapat dihapus |
| `DELETE /admin/sdgs/{tahun}` | skor SDGs tidak dapat dihapus |
| `GET /admin/residents/dusuns` | daftar dusun hanya ada di balik `manage-stunting` |

Dua penghapusan sengaja **ditolak** ketika masih ada data yang bergantung:
tahun anggaran dan kategori APBDes yang masih memuat rincian. Menghapusnya
berantai akan melenyapkan angka yang menjadi rujukan publik pada halaman
transparansi hanya karena satu ketukan keliru; servernya menjawab 422 beserta
jumlah rincian yang harus dibereskan lebih dahulu.

Pada modul yang menyimpan dengan `updateOrCreate` (Stunting, IDM, SDGs) tidak
ada endpoint ubah tersendiri — dan itu memang disengaja. Yang ditambahkan di
sisi CMS adalah tombol sunting yang MEMUAT ULANG baris ke formulir, supaya
operator tidak mengetik ulang seluruh baris demi mengoreksi satu angka.

Satu perubahan aturan validasi: `PUT /admin/bansos/penerima/{id}` kini
menerima permintaan tanpa `nik`. Daftar penerima hanya memuat NIK tersamar,
sehingga memaksa operator mengetik ulang 16 digit demi mengoreksi nominal
justru mengundang salah ketik pada kolom yang paling sensitif.

**Yang sengaja TIDAK dilengkapi:** pengaduan, permohonan informasi PPID, dan
pengajuan surat. Ketiganya kiriman warga — operator menanggapi statusnya,
tetapi tidak membuat atau menghapusnya. Nomor tiket ketiganya beredar di
tangan warga sebagai bukti, dan menghapus barisnya berarti mematikan
pelacakan yang mereka pegang.

### A10. Perbaikan: validator lebih longgar daripada skema basis data

Dilaporkan pemilik produk saat menambah data penduduk dari CMS:

```
SQLSTATE[01000]: Warning: 1265 Data truncated for column 'pendidikan_terakhir'
```

`residents.pendidikan_terakhir` dan `residents.agama` adalah kolom **ENUM**,
tetapi `ResidentController::validasi()` memvalidasinya sebagai string bebas
(`max:50` dan `max:20`). Nilai di luar daftar — "PAUD", "atheis" — lolos
validasi, lalu ditolak MySQL. Yang dilihat operator galat 500, bukan pesan yang
menyebutkan pilihan yang sah. Pengimpor CSV mengidap separuh cacat yang sama:
ia memeriksa `agama` tetapi **tidak** `pendidikan_terakhir`, sehingga satu baris
menyimpang menggagalkan seluruh impor dengan galat basis data alih-alih
dilaporkan sebagai baris bermasalah.

Perbaikannya:

- Nilai ENUM diangkat menjadi konstanta pada model `Resident` — satu sumber
  yang dibaca validator API, pengimpor CSV, dan pilihan pada formulir CMS.
- Formulir CMS memakai dropdown, bukan ketikan bebas, dan daftarnya **diambil
  dari server** (`GET /admin/residents/opsi`). Menyalin daftar ke berkas React
  hanya memindahkan masalahnya: salinan yang menyimpang menghasilkan pilihan
  yang tampak sah di layar tetapi ditolak saat disimpan.

Dua cacat sekelas ikut ditemukan saat menyisir sisanya, keduanya "indeks unik
basis data tanpa aturan validasi yang sepadan":

- **NIK penduduk ganda** berakhir galat 500. Petunjuknya ada di kode: parameter
  `$abaikan` pada `validasi()` sudah disiapkan untuk pengecualian saat
  menyunting, tetapi tidak pernah dipakai. Keunikan kini diperiksa lewat blind
  index `nik_hash` — `Rule::unique` biasa tidak akan pernah cocok karena NIK
  disimpan terenkripsi non-deterministik.
- **Penerima bansos ganda** (orang yang sama, bantuan & tahun yang sama) juga
  berakhir 500; indeks `penerima_unik_per_bantuan` menegakkannya di basis data
  tanpa pasangan validasinya.

Penyisiran menyeluruh atas seluruh kolom ENUM dan kolom berpanjang tetap tidak
menemukan ketidakcocokan lain: modul selain kependudukan sudah memakai
`Rule::in` dan `max:` yang sepadan dengan skemanya.

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
