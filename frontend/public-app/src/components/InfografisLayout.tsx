import { NavLink, Outlet } from 'react-router-dom'

/**
 * Kerangka modul Infografis — PRD 3.2.
 *
 * Situs referensi memakai satu halaman induk dengan 6 sub-tab, bukan enam
 * halaman berdiri sendiri. Pola itu direplikasi di sini agar pengunjung dapat
 * berpindah antar-dimensi data tanpa kehilangan konteks.
 */
const TAB = [
  { ke: '/infografis/penduduk', label: 'Penduduk' },
  { ke: '/infografis/apbdes', label: 'APBDes' },
  { ke: '/infografis/stunting', label: 'Stunting' },
  { ke: '/infografis/bansos', label: 'Bansos' },
  { ke: '/infografis/idm', label: 'IDM' },
  { ke: '/infografis/sdgs', label: 'SDGs' },
]

export function InfografisLayout() {
  return (
    <div>
      {/* Menempel di bawah header utama saat digulir — enam sub-halaman yang
          saling terkait sulit dijelajahi bila navigasinya ikut tergulir. */}
      <div className="sticky top-17 z-30 border-b border-navy/10 bg-white/95 backdrop-blur">
        <nav
          aria-label="Sub-navigasi infografis"
          className="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-6"
        >
          {TAB.map((tab) => (
            <NavLink
              key={tab.ke}
              to={tab.ke}
              className={({ isActive }) =>
                `-mb-px shrink-0 border-b-2 px-4 py-3.5 text-sm font-semibold whitespace-nowrap transition ${
                  isActive
                    ? 'border-gold text-navy'
                    : 'border-transparent text-slate-500 hover:text-navy'
                }`
              }
            >
              {tab.label}
            </NavLink>
          ))}
        </nav>
      </div>

      <Outlet />
    </div>
  )
}
