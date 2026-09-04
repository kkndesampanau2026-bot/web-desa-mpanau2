import { useEffect, useRef, useState, type ReactNode } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import {
  Bell,
  FileSignature,
  FileText,
  Gauge,
  HandCoins,
  HeartPulse,
  Images,
  Landmark,
  LayoutDashboard,
  LogOut,
  MapPin,
  MessageSquareWarning,
  Newspaper,
  Settings,
  Store,
  Target,
  Users,
  UsersRound,
  Wallet,
  type LucideIcon,
} from 'lucide-react'
import { punyaIzin, useHalaman } from '@/types/inertia'

/**
 * Kerangka dashboard admin.
 *
 * Menu disaring menurut permission pengguna — murni demi kenyamanan, agar
 * operator tidak melihat menu yang pasti ditolak server. Otorisasi
 * sesungguhnya tetap ditegakkan backend pada setiap route.
 *
 * Menu untuk modul yang belum berpindah ke Inertia sengaja belum didaftarkan
 * di sini agar tidak ada tautan yang menuju halaman kosong.
 */
const MENU: { ke: string; label: string; izin: string; ikon: LucideIcon; ujung?: boolean }[] = [
  { ke: '/admin', label: 'Dashboard', izin: 'view-dashboard', ikon: LayoutDashboard, ujung: true },
  { ke: '/admin/profil', label: 'Profil Desa', izin: 'manage-village-profile', ikon: Landmark },
  { ke: '/admin/sotk-bpd', label: 'SOTK & BPD', izin: 'manage-officials', ikon: Users },
  { ke: '/admin/berita', label: 'Berita', izin: 'manage-news', ikon: Newspaper },
  { ke: '/admin/galeri', label: 'Galeri', izin: 'manage-gallery', ikon: Images },
  { ke: '/admin/penduduk', label: 'Data Penduduk', izin: 'manage-population-data', ikon: UsersRound },
  { ke: '/admin/apbdes', label: 'APBDes', izin: 'manage-apbdes', ikon: Wallet },
  { ke: '/admin/stunting', label: 'Stunting', izin: 'manage-stunting', ikon: HeartPulse },
  { ke: '/admin/idm', label: 'IDM', izin: 'manage-idm', ikon: Gauge },
  { ke: '/admin/sdgs', label: 'SDGs Desa', izin: 'manage-sdgs', ikon: Target },
  { ke: '/admin/ekonomi', label: 'Potensi & Ekonomi', izin: 'manage-potential', ikon: Store },
  { ke: '/admin/peta', label: 'Titik Lokasi', izin: 'manage-poi', ikon: MapPin },
  { ke: '/admin/pengaduan', label: 'Pengaduan', izin: 'respond-complaint', ikon: MessageSquareWarning },
  { ke: '/admin/bansos', label: 'Bantuan Sosial', izin: 'manage-bansos', ikon: HandCoins },
  { ke: '/admin/ppid', label: 'PPID', izin: 'manage-ppid-content', ikon: FileText },
  { ke: '/admin/surat', label: 'Surat Pengantar', izin: 'manage-letter-request', ikon: FileSignature },
  { ke: '/admin/pengaturan', label: 'Pengaturan Umum', izin: 'manage-settings', ikon: Settings },
]

/**
 * `judul` mengisi tab peramban, menjadi konteks pada breadcrumb topbar, dan
 * menghemat satu `<Head>` di tiap halaman: layar CMS mengembalikan satu elemen
 * akar (form atau div), sehingga menambahkan <Head> di dalamnya menuntut
 * pembungkus fragment tambahan yang tidak memberi manfaat apa pun.
 */
export function LayoutAdmin({
  judul,
  children,
}: {
  judul?: string
  children: ReactNode
}) {
  const { props, url } = useHalaman()
  const pengguna = props.auth.user
  const notifikasi = props.notifikasi_pengaduan
  const jumlahPengaduanBaru = notifikasi?.jumlah ?? 0

  const [notifikasiTerbuka, setNotifikasiTerbuka] = useState(false)
  const notifikasiRef = useRef<HTMLDivElement>(null)

  // Menutup dropdown saat mengklik di luar panel atau menekan Escape — pola
  // standar untuk menu mengambang yang tidak punya overlay sendiri.
  useEffect(() => {
    if (!notifikasiTerbuka) return

    function tutupJikaDiLuar(e: MouseEvent) {
      if (notifikasiRef.current && !notifikasiRef.current.contains(e.target as Node)) {
        setNotifikasiTerbuka(false)
      }
    }

    function tutupJikaEscape(e: KeyboardEvent) {
      if (e.key === 'Escape') setNotifikasiTerbuka(false)
    }

    document.addEventListener('mousedown', tutupJikaDiLuar)
    document.addEventListener('keydown', tutupJikaEscape)

    return () => {
      document.removeEventListener('mousedown', tutupJikaDiLuar)
      document.removeEventListener('keydown', tutupJikaEscape)
    }
  }, [notifikasiTerbuka])

  const menuTampil = MENU.filter((m) => punyaIzin(pengguna, m.izin))
  const jalur = url.split('?')[0]

  function aktif(ke: string, ujung?: boolean) {
    return ujung ? jalur === ke : jalur === ke || jalur.startsWith(`${ke}/`)
  }

  const inisial = (pengguna?.nama ?? 'A').charAt(0).toUpperCase()

  return (
    <div className="area-admin flex min-h-screen bg-[#f8f9ff]">
      {judul && <Head title={judul} />}

      <aside className="hidden w-64 shrink-0 border-r border-slate-200 bg-white lg:flex lg:flex-col">
        <div className="flex items-center gap-3 border-b border-slate-200 px-6 py-4">
          <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-teal-600 text-white">
            <Landmark className="size-5" aria-hidden="true" />
          </span>
          <span className="min-w-0">
            <span className="block font-bold tracking-tight text-teal-700">Dashboard Desa</span>
            <span className="block text-xs font-semibold text-slate-500">
              Sistem Informasi Desa
            </span>
          </span>
        </div>

        <nav aria-label="Navigasi dashboard" className="flex-1 space-y-1 overflow-y-auto p-3">
          {menuTampil.map((m) => {
            const ini = aktif(m.ke, m.ujung)

            return (
              <Link
                key={m.ke}
                href={m.ke}
                aria-current={ini ? 'page' : undefined}
                className={`flex items-center gap-3 rounded-lg py-2.5 text-sm font-medium transition ${
                  ini
                    ? 'border-l-4 border-teal-700 bg-teal-600/10 pr-3 pl-3 text-teal-700'
                    : 'px-3 text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                }`}
              >
                <m.ikon className="size-[18px] shrink-0" aria-hidden="true" />
                {m.label}
              </Link>
            )
          })}
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="border-b border-slate-200 bg-white">
          <div className="flex items-center justify-between gap-4 px-6 py-3">
            <div className="flex min-w-0 items-center gap-2">
              <span className="hidden text-sm text-slate-500 sm:inline">Dashboard Admin</span>
              <span className="hidden text-slate-300 sm:inline" aria-hidden="true">
                ›
              </span>
              <span className="truncate text-lg font-bold tracking-tight text-teal-700">
                {judul ?? 'Dashboard'}
              </span>
            </div>

            <div className="flex shrink-0 items-center gap-3">
              <div ref={notifikasiRef} className="relative hidden sm:block">
                <button
                  type="button"
                  onClick={() => setNotifikasiTerbuka((t) => !t)}
                  aria-expanded={notifikasiTerbuka}
                  aria-haspopup="true"
                  className="relative inline-flex rounded-full p-2 text-slate-500 hover:bg-slate-100"
                  aria-label={
                    jumlahPengaduanBaru > 0
                      ? `${jumlahPengaduanBaru} pengaduan baru belum ditanggapi`
                      : 'Tidak ada pengaduan baru'
                  }
                >
                  <Bell className="size-5" />
                  {jumlahPengaduanBaru > 0 && (
                    <span
                      aria-hidden="true"
                      className="absolute top-0.5 right-0.5 grid h-4.5 min-w-4.5 place-items-center rounded-full bg-rose-600 px-1 text-[10px] leading-none font-semibold text-white"
                    >
                      {jumlahPengaduanBaru > 99 ? '99+' : jumlahPengaduanBaru}
                    </span>
                  )}
                </button>

                {notifikasiTerbuka && (
                  <div
                    role="menu"
                    aria-label="Notifikasi pengaduan"
                    className="absolute top-full right-0 z-20 mt-2 w-80 rounded-xl border border-slate-200 bg-white py-2 shadow-lg"
                  >
                    <div className="flex items-center justify-between px-4 py-1.5">
                      <p className="text-sm font-semibold text-slate-900">Pengaduan Baru</p>
                      {jumlahPengaduanBaru > 0 && (
                        <span className="text-xs text-slate-500">
                          {jumlahPengaduanBaru} belum ditanggapi
                        </span>
                      )}
                    </div>

                    {!notifikasi || notifikasi.daftar.length === 0 ? (
                      <p className="px-4 py-6 text-center text-sm text-slate-500">
                        Tidak ada pengaduan baru.
                      </p>
                    ) : (
                      <ul className="max-h-80 overflow-y-auto">
                        {notifikasi.daftar.map((p) => (
                          <li key={p.id} className="border-t border-slate-100 first:border-t-0">
                            <Link
                              href={`/admin/pengaduan?buka=${p.id}`}
                              onClick={() => setNotifikasiTerbuka(false)}
                              className="block px-4 py-2.5 hover:bg-slate-50"
                            >
                              <div className="flex items-center justify-between gap-2">
                                <p className="truncate text-sm font-medium text-slate-900">
                                  {p.nama}
                                </p>
                                <span className="shrink-0 text-xs text-slate-400">{p.dibuat}</span>
                              </div>
                              <p className="truncate text-xs text-slate-500">
                                {p.kategori_pengaduan} · {p.isi_ringkas}
                              </p>
                            </Link>
                          </li>
                        ))}
                      </ul>
                    )}

                    <div className="border-t border-slate-100 px-4 pt-2">
                      <Link
                        href="/admin/pengaduan"
                        onClick={() => setNotifikasiTerbuka(false)}
                        className="block py-1.5 text-center text-sm font-medium text-teal-700 hover:underline"
                      >
                        Lihat semua pengaduan
                      </Link>
                    </div>
                  </div>
                )}
              </div>

              <div className="hidden text-right sm:block">
                <p className="text-sm font-medium text-slate-900">{pengguna?.nama}</p>
                <p className="text-xs text-slate-500">{pengguna?.roles.join(', ')}</p>
              </div>

              <span className="grid size-9 shrink-0 place-items-center rounded-full bg-teal-600 text-sm font-semibold text-white">
                {inisial}
              </span>

              {/*
                Keluar wajib POST: sebagai tautan GET ia dapat dipicu oleh
                prefetch peramban atau tag <img> di situs lain, membuat operator
                terlempar keluar tanpa pernah menekan apa pun.
              */}
              <button
                onClick={() => router.post('/admin/keluar')}
                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50"
              >
                <LogOut className="size-4" aria-hidden="true" />
                <span className="hidden sm:inline">Keluar</span>
              </button>
            </div>
          </div>

          {/* Navigasi ringkas untuk layar kecil. */}
          <nav
            aria-label="Navigasi dashboard"
            className="flex gap-1 overflow-x-auto border-t border-slate-200 px-3 py-2 lg:hidden"
          >
            {menuTampil.map((m) => {
              const ini = aktif(m.ke, m.ujung)

              return (
                <Link
                  key={m.ke}
                  href={m.ke}
                  aria-current={ini ? 'page' : undefined}
                  className={`flex shrink-0 items-center gap-1.5 rounded-md px-2.5 py-1.5 text-sm font-medium ${
                    ini ? 'bg-teal-600 text-white' : 'text-slate-600 hover:bg-slate-100'
                  }`}
                >
                  <m.ikon className="size-4 shrink-0" aria-hidden="true" />
                  {m.label}
                </Link>
              )
            })}
          </nav>
        </header>

        <main className="flex-1 p-6">{children}</main>
      </div>
    </div>
  )
}
