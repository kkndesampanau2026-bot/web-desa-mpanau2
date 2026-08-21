import { useEffect, useState, type ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { Clock, Mail, MapPin, Menu, Phone, ShieldCheck, X } from 'lucide-react'
import { labelHari } from '@/lib/format'
import { useHalaman } from '@/types/inertia'
import { WidgetStatistik } from '@/Components/WidgetStatistik'

/**
 * Kerangka halaman publik — PRD 3.1.
 *
 * Navigasi utama memuat 7 menu persis seperti situs referensi. Pengaduan
 * sengaja TIDAK diletakkan di navigasi utama melainkan di footer/quick-link,
 * mengikuti pola situs referensi.
 *
 * Identitas desa datang dari prop bersama Inertia, sehingga header dan footer
 * sudah terisi pada render pertama — tidak ada lagi kedipan nama desa kosong
 * sementara permintaan `/settings` berjalan.
 */
const MENU_UTAMA = [
  { ke: '/', label: 'Beranda', ujung: true },
  { ke: '/profil', label: 'Profil Desa' },
  { ke: '/infografis', label: 'Infografis' },
  { ke: '/listing', label: 'Peta Desa' },
  { ke: '/berita', label: 'Berita' },
  { ke: '/belanja', label: 'Belanja' },
  { ke: '/ppid', label: 'PPID' },
]

/**
 * Menandai menu yang sedang dibuka.
 *
 * `react-router` dulu menghitung ini sendiri lewat `NavLink`. Inertia hanya
 * menyediakan URL mentah, jadi pencocokannya ditulis di sini: menu "Beranda"
 * harus cocok persis (kalau tidak, ia menyala di semua halaman), sisanya
 * cocok bila URL berada di bawahnya (agar `/berita/judul-artikel` tetap
 * menyalakan menu Berita).
 */
function sedangAktif(url: string, ke: string, ujung?: boolean): boolean {
  const jalur = url.split('?')[0]

  return ujung ? jalur === ke : jalur === ke || jalur.startsWith(`${ke}/`)
}

export function LayoutPublik({ children }: { children: ReactNode }) {
  const { props, url } = useHalaman()
  const pengaturan = props.pengaturan
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

  return (
    <div className="flex min-h-screen flex-col bg-cream">
      <a
        href="#konten-utama"
        className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-gold focus:px-4 focus:py-2 focus:font-semibold focus:text-navy"
      >
        Lompat ke konten utama
      </a>

      {/* Bilah tipis berisi identitas resmi — menegaskan ini kanal pemerintah. */}
      <div className="bg-navy-dark text-white/70">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-1 px-6 py-1.5 text-xs">
          <p className="flex items-center gap-1.5">
            <ShieldCheck className="size-3.5 shrink-0 text-gold" aria-hidden="true" />
            Situs Resmi Pemerintah {namaDesa}
          </p>
          {pengaturan?.kode_wilayah && (
            <p className="tabular-nums">Kode Wilayah {pengaturan.kode_wilayah}</p>
          )}
        </div>
      </div>

      <header className="sticky top-0 z-40 border-b-4 border-gold bg-navy shadow-sm">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3">
          <Link href="/" className="flex items-center gap-3">
            {pengaturan?.logo ? (
              <img src={pengaturan.logo} alt="" className="size-10 rounded-full object-cover" />
            ) : (
              <span
                aria-hidden="true"
                className="font-heading grid size-10 shrink-0 place-items-center rounded-full border-2 border-gold text-sm font-bold text-gold"
              >
                DM
              </span>
            )}
            <span className="min-w-0">
              <span className="font-heading block leading-tight font-bold text-white">
                {namaDesa}
              </span>
              {pengaturan?.wilayah.kecamatan && (
                <span className="block text-xs text-white/60">
                  Kec. {pengaturan.wilayah.kecamatan}
                </span>
              )}
            </span>
          </Link>

          <nav aria-label="Navigasi utama" className="hidden items-center gap-1 lg:flex">
            {MENU_UTAMA.map((menu) => {
              const aktif = sedangAktif(url, menu.ke, menu.ujung)

              return (
                <Link
                  key={menu.label}
                  href={menu.ke}
                  aria-current={aktif ? 'page' : undefined}
                  className={`rounded-lg px-3 py-2 text-sm font-semibold transition ${
                    aktif
                      ? 'bg-white/10 text-gold'
                      : 'text-white/80 hover:bg-white/5 hover:text-white'
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
            className="rounded-lg p-2 text-white transition hover:bg-white/10 lg:hidden"
          >
            {menuTerbuka ? <X className="size-6" /> : <Menu className="size-6" />}
          </button>
        </div>

        {menuTerbuka && (
          <nav
            id="menu-ponsel"
            aria-label="Navigasi utama"
            className="border-t border-white/10 bg-navy-light lg:hidden"
          >
            <div className="mx-auto max-w-6xl px-4 py-2">
              {MENU_UTAMA.map((menu) => {
                const aktif = sedangAktif(url, menu.ke, menu.ujung)

                return (
                  <Link
                    key={menu.label}
                    href={menu.ke}
                    aria-current={aktif ? 'page' : undefined}
                    className={`block rounded-lg px-4 py-3 text-sm font-semibold transition ${
                      aktif ? 'bg-white/10 text-gold' : 'text-white/85 hover:bg-white/5'
                    }`}
                  >
                    {menu.label}
                  </Link>
                )
              })}
            </div>
          </nav>
        )}
      </header>

      <main id="konten-utama" className="flex-1">
        {children}
      </main>

      <footer className="bg-navy text-white/75">
        <div className="mx-auto max-w-6xl px-6 py-12">
          <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <h2 className="font-heading text-base font-bold text-white">{namaDesa}</h2>
              {wilayah && <p className="mt-2 text-sm">{wilayah}</p>}

              <address className="mt-4 space-y-2 text-sm not-italic">
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
              </address>
            </div>

            {pengaturan?.jam_kerja && Object.keys(pengaturan.jam_kerja).length > 0 && (
              <div>
                <h2 className="flex items-center gap-2 text-sm font-bold tracking-wide text-white uppercase">
                  <Clock className="size-4 text-gold" aria-hidden="true" />
                  Jam Pelayanan
                </h2>
                <dl className="mt-4 space-y-1.5 text-sm">
                  {Object.entries(pengaturan.jam_kerja).map(([hari, jam]) => (
                    <div key={hari} className="flex justify-between gap-4">
                      <dt>{labelHari(hari)}</dt>
                      <dd className="text-white/60 tabular-nums">
                        {jam.libur ? 'Libur' : `${jam.buka ?? '—'}–${jam.tutup ?? '—'}`}
                      </dd>
                    </div>
                  ))}
                </dl>
              </div>
            )}

            {pengaturan?.nomor_telepon_penting &&
              pengaturan.nomor_telepon_penting.length > 0 && (
                <div>
                  <h2 className="text-sm font-bold tracking-wide text-white uppercase">
                    Nomor Penting
                  </h2>
                  <dl className="mt-4 space-y-1.5 text-sm">
                    {pengaturan.nomor_telepon_penting.map((n) => (
                      <div key={n.nama_layanan} className="flex justify-between gap-4">
                        <dt>{n.nama_layanan}</dt>
                        <dd className="text-white/60 tabular-nums">{n.nomor}</dd>
                      </div>
                    ))}
                  </dl>
                </div>
              )}

            <div>
              <h2 className="text-sm font-bold tracking-wide text-white uppercase">
                Layanan Warga
              </h2>
              <ul className="mt-4 space-y-2 text-sm">
                {[
                  ['/pengaduan', 'Kirim Pengaduan'],
                  ['/pengaduan/lacak', 'Lacak Pengaduan'],
                  ['/ppid/permintaan', 'Permohonan Informasi'],
                  ['/infografis/bansos', 'Cek Penerima Bansos'],
                ].map(([ke, label]) => (
                  <li key={ke}>
                    <Link href={ke} className="underline-offset-4 hover:text-gold hover:underline">
                      {label}
                    </Link>
                  </li>
                ))}
              </ul>

              {pengaturan?.sosial_media && pengaturan.sosial_media.length > 0 && (
                <ul className="mt-5 flex flex-wrap gap-2">
                  {pengaturan.sosial_media.map((s) => (
                    <li key={s.platform}>
                      <a
                        href={s.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="rounded-full border border-white/20 px-3 py-1 text-xs transition hover:border-gold hover:text-gold"
                      >
                        {s.platform}
                      </a>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>

          <div className="mt-10 border-t border-white/10 pt-8">
            <WidgetStatistik />
          </div>

          <p className="mt-8 border-t border-white/10 pt-6 text-xs text-white/50">
            © {new Date().getFullYear()} Pemerintah {namaDesa}
            {pengaturan?.kode_wilayah && <> · Kode Wilayah {pengaturan.kode_wilayah}</>}
          </p>
        </div>
      </footer>
    </div>
  )
}
