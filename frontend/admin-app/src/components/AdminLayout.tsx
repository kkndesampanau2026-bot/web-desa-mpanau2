import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '@/features/auth/AuthContext'

/**
 * Kerangka dashboard admin.
 *
 * Menu disaring menurut permission pengguna — murni demi kenyamanan, agar
 * operator tidak melihat menu yang pasti ditolak server. Otorisasi
 * sesungguhnya tetap ditegakkan backend pada setiap endpoint.
 *
 * Menu untuk modul fase berikutnya sengaja belum didaftarkan di sini agar
 * tidak ada tautan yang menuju halaman kosong.
 */
const MENU = [
  { ke: '/dashboard', label: 'Dashboard', izin: 'view-dashboard' },
  { ke: '/profil', label: 'Profil Desa', izin: 'manage-village-profile' },
  { ke: '/sotk-bpd', label: 'SOTK & BPD', izin: 'manage-officials' },
  { ke: '/berita', label: 'Berita', izin: 'manage-news' },
  { ke: '/galeri', label: 'Galeri', izin: 'manage-gallery' },
  { ke: '/penduduk', label: 'Data Penduduk', izin: 'manage-population-data' },
  { ke: '/ekonomi', label: 'Potensi & Ekonomi', izin: 'manage-potential' },
  { ke: '/peta', label: 'Titik Lokasi', izin: 'manage-poi' },
  { ke: '/pengaduan', label: 'Pengaduan', izin: 'respond-complaint' },
  { ke: '/bansos', label: 'Bantuan Sosial', izin: 'manage-bansos' },
  { ke: '/ppid', label: 'PPID', izin: 'manage-ppid-content' },
  { ke: '/pengaturan', label: 'Pengaturan Umum', izin: 'manage-settings' },
]

export function AdminLayout() {
  const { user, logout, punyaIzin } = useAuth()

  // punyaIzin sudah menangani kasus Super Admin (lihat AuthProvider).
  const menuTampil = MENU.filter((m) => punyaIzin(m.izin))

  return (
    <div className="flex min-h-screen bg-slate-100">
      <aside className="hidden w-60 shrink-0 border-r border-slate-200 bg-white lg:block">
        <div className="border-b border-slate-200 px-5 py-4">
          <p className="font-semibold text-slate-900">Dashboard Desa</p>
          <p className="mt-0.5 truncate text-xs text-slate-500">{user?.nama}</p>
        </div>

        <nav aria-label="Navigasi dashboard" className="space-y-0.5 p-3">
          {menuTampil.map((m) => (
            <NavLink
              key={m.ke}
              to={m.ke}
              className={({ isActive }) =>
                `block rounded-lg px-3 py-2 text-sm transition ${
                  isActive
                    ? 'bg-slate-900 text-white'
                    : 'text-slate-700 hover:bg-slate-100'
                }`
              }
            >
              {m.label}
            </NavLink>
          ))}
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="border-b border-slate-200 bg-white">
          <div className="flex items-center justify-between gap-4 px-6 py-3">
            {/* Navigasi ringkas untuk layar kecil. */}
            <nav aria-label="Navigasi dashboard" className="flex gap-1 overflow-x-auto lg:hidden">
              {menuTampil.map((m) => (
                <NavLink
                  key={m.ke}
                  to={m.ke}
                  className={({ isActive }) =>
                    `shrink-0 rounded-md px-2.5 py-1.5 text-sm ${
                      isActive ? 'bg-slate-900 text-white' : 'text-slate-600'
                    }`
                  }
                >
                  {m.label}
                </NavLink>
              ))}
            </nav>

            <span className="hidden text-sm text-slate-500 lg:block">
              {user?.roles.join(', ')}
            </span>

            <button
              onClick={() => void logout()}
              className="shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50"
            >
              Keluar
            </button>
          </div>
        </header>

        <main className="flex-1 p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
