import { useEffect, useState, type ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { Clock, Mail, MapPin, Menu, Phone, Users, X } from 'lucide-react'
import { formatAngka } from '@/lib/format'
import { useHalaman } from '@/types/inertia'
import { GAYA_WADAH } from '@/Components/ui'
import { MenuAksesibilitas } from '@/Components/MenuAksesibilitas'
import { AduanWarga } from '@/Components/AduanWarga'
import { IkonSosialMedia, labelPlatform } from '@/Components/IkonSosialMedia'

/**
 * Kerangka halaman publik — PRD 3.1.
 *
 * Bilah atas navy-gelap berisi identitas resmi & penghitung pengunjung, bilah
 * navigasi navy dengan menu berpil emas pada item aktif, footer empat kolom,
 * serta dua tombol mengambang (menu aksesibilitas di kiri bawah dan "Aduan
 * Warga" di kanan bawah).
 *
 * Ketiga bagian kerangka memakai `GAYA_WADAH` yang sama dengan isi halaman,
 * jadi logo, judul halaman, dan kolom pertama footer jatuh pada satu garis
 * vertikal di setiap ukuran layar. Tingginya pun ditetapkan (h-8 + h-16,
 * menjadi h-18 mulai sm) supaya sama dengan `--tinggi-header` yang dipakai
 * sub-navigasi lengket dan lompatan anchor — bukan angka yang ditebak ulang
 * di tiap berkas.
 *
 * Identitas desa datang dari prop bersama Inertia, sehingga header & footer
 * sudah terisi pada render pertama — tanpa kedipan nama desa kosong.
 */
interface ItemMenu {
  ke: string
  label: string
  ujung?: boolean
}

/**
 * Menu datar, tanpa cabang.
 *
 * "Potensi & Ekonomi" sempat bercabang ke /wisata dan /ekonomi. Keduanya kini
 * tampil sebagai kategori di dalam /potensi itu sendiri — berikut halaman
 * detailnya — sehingga panel yang harus dibuka dulu hanya menambah satu
 * ketukan menuju isi yang sama.
 */
const MENU_UTAMA: ItemMenu[] = [
  { ke: '/', label: 'Home', ujung: true },
  { ke: '/profil', label: 'Profil Desa' },
  { ke: '/infografis', label: 'Infografis' },
  { ke: '/listing', label: 'Listing' },
  { ke: '/potensi', label: 'Potensi & Ekonomi' },
  { ke: '/berita', label: 'Berita Desa' },
  { ke: '/ppid', label: 'PPID' },
  { ke: '/layanan-mandiri', label: 'Layanan Mandiri' },
]

/**
 * Menandai menu yang sedang dibuka.
 *
 * Inertia hanya menyediakan URL mentah, jadi pencocokannya ditulis di sini:
 * "Home" harus cocok persis (kalau tidak, ia menyala di semua halaman),
 * sisanya cocok bila URL berada di bawahnya (agar `/berita/judul` tetap
 * menyalakan menu Berita Desa).
 */
function sedangAktif(url: string, menu: ItemMenu): boolean {
  const jalur = url.split('?')[0]

  return menu.ujung
    ? jalur === menu.ke
    : jalur === menu.ke || jalur.startsWith(`${menu.ke}/`)
}

/**
 * Nomor untuk atribut `href="tel:"`.
 *
 * Operator menuliskan nomor apa adanya di CMS — "(0451) 123-456" atau
 * "0812 3456 7890" — dan spasi maupun tanda kurung membuat sebagian ponsel
 * gagal menyalakan panggilan. Yang ditampilkan tetap teks asli operator;
 * yang dibersihkan hanya tautannya.
 */
function tautanTelepon(nomor: string): string {
  return nomor.replace(/[^\d+]/g, '')
}

export function LayoutPublik({ children }: { children: ReactNode }) {
  const { props, url } = useHalaman()
  const pengaturan = props.pengaturan
  const totalPengunjung = props.statistik_kunjungan?.total
  const [menuTerbuka, setMenuTerbuka] = useState(false)

  // Menu ponsel ditutup setiap kali berpindah halaman; membiarkannya terbuka
  // akan menutupi konten yang baru saja dituju pengunjung.
  useEffect(() => {
    setMenuTerbuka(false)
  }, [url])

  const namaDesa = pengaturan?.nama_desa ?? 'Desa Mpanau'
  const wilayah = [
    pengaturan?.wilayah.kecamatan && `Kec. ${pengaturan.wilayah.kecamatan}`,
    pengaturan?.wilayah.kabupaten && `Kab. ${pengaturan.wilayah.kabupaten}`,
    pengaturan?.wilayah.provinsi,
  ]
    .filter(Boolean)
    .join(', ')

  // Baris "Kabupaten • Provinsi" di bawah nama desa pada header.
  const wilayahRingkas = [pengaturan?.wilayah.kabupaten, pengaturan?.wilayah.provinsi]
    .filter(Boolean)
    .join(' • ')

  const jamSenin = pengaturan?.jam_kerja?.senin

  // Keduanya diisi lewat CMS → Pengaturan Umum, dan sudah ikut pada prop
  // bersama setiap halaman publik — tidak perlu diambil ulang di sini.
  const sosialMedia = pengaturan?.sosial_media ?? []
  const nomorPenting = pengaturan?.nomor_telepon_penting ?? []

  return (
    <div className="bg-cream">
      <a
        href="#konten-utama"
        className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-gold focus:px-4 focus:py-2 focus:font-semibold focus:text-navy"
      >
        Lompat ke konten utama
      </a>

      <header className="sticky top-0 z-40 shadow-md">
        {/*
          Bilah atas: identitas resmi & penghitung pengunjung.

          Nama wilayah disembunyikan di bawah sm. Pada ponsel ia terpaksa
          membungkus ke baris kedua, menaikkan tinggi header yang sudah
          menyita layar — sementara isinya sudah diulang di footer.
        */}
        <div className="bg-navy-dark">
          <div className={`${GAYA_WADAH} flex h-8 items-center justify-between gap-4`}>
            <p className="hidden truncate text-[11px] text-white/60 sm:block">
              Pemerintah {namaDesa}
              {wilayah && ` • ${wilayah}`}
            </p>

            {totalPengunjung != null && (
              <span className="flex shrink-0 items-center gap-1.5 text-[11px] text-white/70">
                <Users className="size-3 shrink-0" aria-hidden="true" />
                Pengunjung
                <span className="font-semibold text-gold tabular-nums">
                  {formatAngka(totalPengunjung)}
                </span>
              </span>
            )}
          </div>
        </div>

        {/* Bilah navigasi utama. */}
        <div className="bg-navy">
          <div className={`${GAYA_WADAH} flex h-16 items-center justify-between gap-4 sm:h-18`}>
            <Link href="/" className="flex min-w-0 items-center gap-3">
              {pengaturan?.logo ? (
                <img
                  src={pengaturan.logo}
                  alt=""
                  className="size-10 shrink-0 rounded-full object-cover sm:size-11"
                />
              ) : (
                <span
                  aria-hidden="true"
                  className="font-heading grid size-10 shrink-0 place-items-center rounded-full border-2 border-gold text-sm font-bold text-gold sm:size-11"
                >
                  DM
                </span>
              )}
              <span className="min-w-0">
                <span className="font-heading block truncate leading-tight font-bold text-white">
                  {namaDesa}
                </span>
                {wilayahRingkas && (
                  <span className="mt-0.5 block truncate text-[11px] tracking-wide text-gold/90">
                    {wilayahRingkas}
                  </span>
                )}
              </span>
            </Link>

            {/*
              Menu mendatar baru muncul di xl.

              Delapan item dengan label sepanjang "Potensi & Ekonomi" menuntut
              ±800px; di lg (1024px) ia berdesakan dengan logo sampai teksnya
              nyaris bersinggungan. Tablet karena itu memakai panel yang sama
              dengan ponsel — bukan menu mendatar yang dipaksa muat.
            */}
            <nav aria-label="Navigasi utama" className="hidden items-center gap-0.5 xl:flex">
              {MENU_UTAMA.map((menu) => {
                const aktif = sedangAktif(url, menu)

                return (
                  <Link
                    key={menu.label}
                    href={menu.ke}
                    aria-current={aktif ? 'page' : undefined}
                    className={`rounded-md px-3 py-2 text-sm font-semibold whitespace-nowrap transition ${
                      aktif
                        ? 'bg-gold text-navy'
                        : 'text-white/80 hover:bg-white/10 hover:text-white'
                    }`}
                  >
                    {menu.label}
                  </Link>
                )
              })}
            </nav>

            <button
              onClick={() => setMenuTerbuka((t) => !t)}
              aria-expanded={menuTerbuka}
              aria-controls="menu-ponsel"
              aria-label={menuTerbuka ? 'Tutup menu' : 'Buka menu'}
              className="-mr-2 shrink-0 rounded-lg p-2.5 text-white transition hover:bg-white/10 xl:hidden"
            >
              {menuTerbuka ? <X className="size-6" /> : <Menu className="size-6" />}
            </button>
          </div>

          {menuTerbuka && (
            <nav
              id="menu-ponsel"
              aria-label="Navigasi utama"
              className="border-t border-white/10 xl:hidden"
            >
              {/*
                Dua kolom mulai sm: pada tablet, delapan baris setinggi 48px
                memaksa panel menutupi hampir seluruh layar.
              */}
              <div className={`${GAYA_WADAH} grid gap-1 py-3 sm:grid-cols-2`}>
                {MENU_UTAMA.map((menu) => {
                  const aktif = sedangAktif(url, menu)

                  return (
                    <Link
                      key={menu.label}
                      href={menu.ke}
                      aria-current={aktif ? 'page' : undefined}
                      className={`rounded-lg px-4 py-3 text-sm font-semibold transition ${
                        aktif ? 'bg-gold text-navy' : 'text-white/85 hover:bg-white/10'
                      }`}
                    >
                      {menu.label}
                    </Link>
                  )
                })}
              </div>
            </nav>
          )}
        </div>
      </header>

      <main id="konten-utama">{children}</main>

      {/*
        Footer mengikuti konten, tidak didorong ke dasar layar. Ruang sisa di
        bawahnya berwarna sama (lihat latar `html` di app.css), jadi pada
        halaman pendek footer tetap terlihat penuh tanpa jurang krem di
        atasnya.

        Ia juga tidak memakai margin atas sendiri: jarak menuju konten sudah
        disediakan padding bawah `IsiHalaman`, dan menambahkannya di sini
        membuat jarak itu terhitung dua kali.
      */}
      <footer className="border-t-4 border-gold bg-navy-dark text-white/70">
        <div className={`${GAYA_WADAH} py-10 sm:py-12`}>
          {/*
            Lima kolom mulai lg, dengan jarak antar-kolom yang dirapatkan di
            sana dan kembali melebar di xl — pada 1024px, lima kolom ber-gap
            10 menyisakan lebar teks yang terlalu sempit untuk alamat kantor.
          */}
          <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-5 lg:gap-6 xl:gap-10">
            {/* Identitas & deskripsi singkat. */}
            <div>
              <div className="flex items-center gap-2.5">
                {pengaturan?.logo ? (
                  <img src={pengaturan.logo} alt="" className="size-10 rounded-full object-cover" />
                ) : (
                  <span
                    aria-hidden="true"
                    className="font-heading grid size-10 shrink-0 place-items-center rounded-full border-2 border-gold text-xs font-bold text-gold"
                  >
                    DM
                  </span>
                )}
                <span className="font-heading text-lg font-bold text-white">{namaDesa}</span>
              </div>
              <p className="mt-3 text-sm leading-relaxed text-white/70">
                Website resmi Pemerintah {namaDesa}
                {wilayah && `, ${wilayah}`}.
              </p>

              {/*
                Sosial media resmi desa (CMS → Pengaturan Umum). Blok ini
                menghilang seluruhnya bila belum ada satu pun tautan — deretan
                ikon kosong di footer hanya menyisakan pertanyaan.
              */}
              {sosialMedia.length > 0 && (
                <ul className="mt-4 flex flex-wrap gap-2">
                  {sosialMedia.map((sosmed) => (
                    <li key={`${sosmed.platform}-${sosmed.url}`}>
                      <a
                        href={sosmed.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        title={labelPlatform(sosmed.platform)}
                        className="grid size-9 place-items-center rounded-full border border-white/15 text-white/75 transition hover:border-gold hover:bg-gold hover:text-navy"
                      >
                        <IkonSosialMedia platform={sosmed.platform} className="size-4" />
                        <span className="sr-only">{labelPlatform(sosmed.platform)}</span>
                      </a>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            {/* Kontak Kantor Desa. */}
            <div>
              <h2 className="font-heading text-sm font-bold tracking-wide text-gold uppercase">
                Kontak Kantor Desa
              </h2>
              <address className="mt-3 space-y-2 text-sm not-italic">
                {pengaturan?.alamat_kantor && (
                  <p className="flex gap-2.5">
                    <MapPin className="mt-0.5 size-4 shrink-0 text-gold/80" aria-hidden="true" />
                    {pengaturan.alamat_kantor}
                  </p>
                )}
                {pengaturan?.kontak.telepon && (
                  <p className="flex gap-2.5">
                    <Phone className="mt-0.5 size-4 shrink-0 text-gold/80" aria-hidden="true" />
                    {pengaturan.kontak.telepon}
                  </p>
                )}
                {pengaturan?.kontak.email && (
                  <p className="flex gap-2.5">
                    <Mail className="mt-0.5 size-4 shrink-0 text-gold/80" aria-hidden="true" />
                    <a
                      href={`mailto:${pengaturan.kontak.email}`}
                      className="break-all underline-offset-2 hover:text-white hover:underline"
                    >
                      {pengaturan.kontak.email}
                    </a>
                  </p>
                )}
                {jamSenin && !jamSenin.libur && jamSenin.buka && jamSenin.tutup && (
                  <p className="flex gap-2.5">
                    <Clock className="mt-0.5 size-4 shrink-0 text-gold/80" aria-hidden="true" />
                    Senin – Jumat, {jamSenin.buka} – {jamSenin.tutup}
                  </p>
                )}
              </address>
            </div>

            {/* Tautan Cepat. */}
            <div>
              <h2 className="font-heading text-sm font-bold tracking-wide text-gold uppercase">
                Tautan Cepat
              </h2>
              <ul className="mt-3 space-y-2 text-sm">
                {[
                  ['/profil', 'Profil Desa'],
                  ['/potensi?kategori=Pariwisata', 'Wisata Desa'],
                  ['/potensi?kategori=Ekonomi', 'Ekonomi Desa'],
                  ['/ppid', 'PPID Desa'],
                  ['/layanan-mandiri', 'Layanan Mandiri'],
                  ['/berita', 'Berita Desa'],
                ].map(([ke, label]) => (
                  <li key={ke}>
                    <Link href={ke} className="underline-offset-4 hover:text-gold hover:underline">
                      {label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>

            {/*
              Nomor Penting — bersebelahan dengan Tautan Cepat dan memakai
              bentuk daftar yang sama. Nomornya ikut ditampilkan di bawah nama
              layanan, bukan hanya tersembunyi di dalam `href`: sebagian besar
              pengunjung membuka situs ini dari komputer kantor desa, di mana
              tautan `tel:` tidak menelepon apa pun.
            */}
            {nomorPenting.length > 0 && (
              <div>
                <h2 className="font-heading text-sm font-bold tracking-wide text-gold uppercase">
                  Nomor Penting
                </h2>
                <ul className="mt-3 space-y-2 text-sm">
                  {nomorPenting.map((nomor) => (
                    <li key={`${nomor.nama_layanan}-${nomor.nomor}`}>
                      <a
                        href={`tel:${tautanTelepon(nomor.nomor)}`}
                        className="group block underline-offset-4 hover:text-gold"
                      >
                        <span className="group-hover:underline">{nomor.nama_layanan}</span>
                        <span className="block text-xs text-white/45 tabular-nums">
                          {nomor.nomor}
                        </span>
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {/* Jam Pelayanan — keterangan naratif. */}
            <div>
              <h2 className="font-heading text-sm font-bold tracking-wide text-gold uppercase">
                Jam Pelayanan
              </h2>
              <p className="mt-3 text-sm leading-relaxed text-white/70">
                Pelayanan administrasi surat-menyurat dan pengaduan warga dilayani setiap hari
                kerja. Untuk kondisi darurat silakan hubungi aparat desa setempat.
              </p>
            </div>
          </div>

          <p className="mt-8 border-t border-white/10 pt-6 text-xs text-white/45 sm:mt-10">
            © {new Date().getFullYear()} Pemerintah {namaDesa}. Seluruh hak cipta dilindungi.
          </p>
        </div>
      </footer>

      {/* Tombol mengambang: aksesibilitas (kiri) & aduan warga (kanan). */}
      <MenuAksesibilitas />
      <AduanWarga />
    </div>
  )
}
