import { useEffect, useState, type ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { Clock, Mail, MapPin, Menu, Phone, Users, X } from 'lucide-react'
import { formatAngka } from '@/lib/format'
import { useHalaman } from '@/types/inertia'
import { MenuAksesibilitas } from '@/Components/MenuAksesibilitas'
import { AduanWarga } from '@/Components/AduanWarga'

/**
 * Kerangka halaman publik — PRD 3.1.
 *
 * Tata letak mengikuti desain Figma: bilah atas navy-gelap berisi identitas
 * resmi & penghitung pengunjung, bilah navigasi navy dengan menu berpil emas
 * pada item aktif, footer empat kolom, serta dua tombol mengambang (menu
 * aksesibilitas di kiri bawah dan "Aduan Warga" di kanan bawah).
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

const gayaMenu = (aktif: boolean) =>
  `rounded-md px-3 py-2 text-sm font-semibold transition ${
    aktif ? 'bg-gold text-navy' : 'text-white/85 hover:bg-white/10 hover:text-white'
  }`

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

  return (
    <div className="flex min-h-screen flex-col bg-cream">
      <a
        href="#konten-utama"
        className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-gold focus:px-4 focus:py-2 focus:font-semibold focus:text-navy"
      >
        Lompat ke konten utama
      </a>

      <header className="sticky top-0 z-40 shadow-lg">
        {/* Bilah atas: identitas resmi & penghitung pengunjung. */}
        <div className="bg-navy-dark">
          <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-1 px-6 py-1.5">
            <p className="text-xs text-white/70">
              Pemerintah {namaDesa}
              {wilayah && ` • ${wilayah}`}
            </p>

            {totalPengunjung != null && (
              <span className="flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs text-white">
                <Users className="size-3.5 shrink-0" aria-hidden="true" />
                Pengunjung:
                <span className="font-bold text-gold tabular-nums">
                  {formatAngka(totalPengunjung)}
                </span>
              </span>
            )}
          </div>
        </div>

        {/* Bilah navigasi utama. */}
        <div className="bg-navy">
          <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3">
            <Link href="/" className="flex items-center gap-3">
              {pengaturan?.logo ? (
                <img src={pengaturan.logo} alt="" className="size-11 rounded-full object-cover" />
              ) : (
                <span
                  aria-hidden="true"
                  className="font-heading grid size-11 shrink-0 place-items-center rounded-full border-2 border-gold text-sm font-bold text-gold"
                >
                  DM
                </span>
              )}
              <span className="min-w-0">
                <span className="font-heading block leading-tight font-bold text-white">
                  {namaDesa}
                </span>
                {wilayahRingkas && (
                  <span className="block text-[11px] tracking-wide text-gold">{wilayahRingkas}</span>
                )}
              </span>
            </Link>

            <nav aria-label="Navigasi utama" className="hidden items-center gap-1 lg:flex">
              {MENU_UTAMA.map((menu) => (
                <Link
                  key={menu.label}
                  href={menu.ke}
                  aria-current={sedangAktif(url, menu) ? 'page' : undefined}
                  className={gayaMenu(sedangAktif(url, menu))}
                >
                  {menu.label}
                </Link>
              ))}
            </nav>

            <button
              onClick={() => setMenuTerbuka((t) => !t)}
              aria-expanded={menuTerbuka}
              aria-controls="menu-ponsel"
              aria-label={menuTerbuka ? 'Tutup menu' : 'Buka menu'}
              className="rounded-lg p-2 text-white transition hover:bg-white/10 lg:hidden"
            >
              {menuTerbuka ? <X className="size-6" /> : <Menu className="size-6" />}
            </button>
          </div>

          {menuTerbuka && (
            <nav
              id="menu-ponsel"
              aria-label="Navigasi utama"
              className="border-t border-white/10 lg:hidden"
            >
              <div className="mx-auto max-w-6xl px-4 py-2">
                {MENU_UTAMA.map((menu) => {
                  const aktif = sedangAktif(url, menu)

                  return (
                    <Link
                      key={menu.label}
                      href={menu.ke}
                      aria-current={aktif ? 'page' : undefined}
                      className={`block rounded-md px-4 py-3 text-sm font-semibold transition ${
                        aktif ? 'bg-gold text-navy' : 'text-white/85 hover:bg-white/5'
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

      <main id="konten-utama" className="flex-1">
        {children}
      </main>

      <footer className="bg-navy-dark text-white/75">
        <div className="mx-auto max-w-6xl px-6 py-12">
          <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            {/* Identitas & deskripsi singkat. */}
            <div>
              <div className="flex items-center gap-2">
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
              <p className="mt-3 text-sm leading-relaxed text-white/80">
                Website resmi Pemerintah {namaDesa}
                {wilayah && `, ${wilayah}`}.
              </p>
            </div>

            {/* Kontak Kantor Desa. */}
            <div>
              <h2 className="font-heading text-base font-bold text-gold">Kontak Kantor Desa</h2>
              <address className="mt-3 space-y-2 text-sm not-italic">
                {pengaturan?.alamat_kantor && (
                  <p className="flex gap-2">
                    <MapPin className="mt-0.5 size-4 shrink-0 text-gold" aria-hidden="true" />
                    {pengaturan.alamat_kantor}
                  </p>
                )}
                {pengaturan?.kontak.telepon && (
                  <p className="flex gap-2">
                    <Phone className="mt-0.5 size-4 shrink-0 text-gold" aria-hidden="true" />
                    {pengaturan.kontak.telepon}
                  </p>
                )}
                {pengaturan?.kontak.email && (
                  <p className="flex gap-2">
                    <Mail className="mt-0.5 size-4 shrink-0 text-gold" aria-hidden="true" />
                    <a
                      href={`mailto:${pengaturan.kontak.email}`}
                      className="underline-offset-2 hover:text-white hover:underline"
                    >
                      {pengaturan.kontak.email}
                    </a>
                  </p>
                )}
                {jamSenin && !jamSenin.libur && jamSenin.buka && jamSenin.tutup && (
                  <p className="flex gap-2">
                    <Clock className="mt-0.5 size-4 shrink-0 text-gold" aria-hidden="true" />
                    Senin – Jumat, {jamSenin.buka} – {jamSenin.tutup}
                  </p>
                )}
              </address>
            </div>

            {/* Tautan Cepat. */}
            <div>
              <h2 className="font-heading text-base font-bold text-gold">Tautan Cepat</h2>
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

            {/* Jam Pelayanan — keterangan naratif. */}
            <div>
              <h2 className="font-heading text-base font-bold text-gold">Jam Pelayanan</h2>
              <p className="mt-3 text-sm leading-relaxed text-white/80">
                Pelayanan administrasi surat-menyurat dan pengaduan warga dilayani setiap hari
                kerja. Untuk kondisi darurat silakan hubungi aparat desa setempat.
              </p>
            </div>
          </div>

          <p className="mt-10 border-t border-white/10 pt-6 text-center text-xs text-white/50">
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
