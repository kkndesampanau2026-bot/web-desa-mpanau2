import type { ReactNode } from 'react'
import { Link, router } from '@inertiajs/react'
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
const MENU = [
  { ke: '/admin', label: 'Dashboard', izin: 'view-dashboard', ujung: true },
]

export function LayoutAdmin({ children }: { children: ReactNode }) {
  const { props, url } = useHalaman()
  const pengguna = props.auth.user

  const menuTampil = MENU.filter((m) => punyaIzin(pengguna, m.izin))
  const jalur = url.split('?')[0]

  function aktif(ke: string, ujung?: boolean) {
    return ujung ? jalur === ke : jalur === ke || jalur.startsWith(`${ke}/`)
  }

  return (
    <div className="area-admin flex min-h-screen bg-slate-100">
      <aside className="hidden w-60 shrink-0 border-r border-slate-200 bg-white lg:block">
        <div className="border-b border-slate-200 px-5 py-4">
          <p className="font-semibold text-slate-900">Dashboard Desa</p>
          <p className="mt-0.5 truncate text-xs text-slate-500">{pengguna?.nama}</p>
        </div>

        <nav aria-label="Navigasi dashboard" className="space-y-0.5 p-3">
          {menuTampil.map((m) => (
            <Link
              key={m.ke}
              href={m.ke}
              aria-current={aktif(m.ke, m.ujung) ? 'page' : undefined}
              className={`block rounded-lg px-3 py-2 text-sm transition ${
                aktif(m.ke, m.ujung)
                  ? 'bg-slate-900 text-white'
                  : 'text-slate-700 hover:bg-slate-100'
              }`}
            >
              {m.label}
            </Link>
          ))}
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="border-b border-slate-200 bg-white">
          <div className="flex items-center justify-between gap-4 px-6 py-3">
            {/* Navigasi ringkas untuk layar kecil. */}
            <nav aria-label="Navigasi dashboard" className="flex gap-1 overflow-x-auto lg:hidden">
              {menuTampil.map((m) => (
                <Link
                  key={m.ke}
                  href={m.ke}
                  aria-current={aktif(m.ke, m.ujung) ? 'page' : undefined}
                  className={`shrink-0 rounded-md px-2.5 py-1.5 text-sm ${
                    aktif(m.ke, m.ujung) ? 'bg-slate-900 text-white' : 'text-slate-600'
                  }`}
                >
                  {m.label}
                </Link>
              ))}
            </nav>

            <span className="hidden text-sm text-slate-500 lg:block">
              {pengguna?.roles.join(', ')}
            </span>

            {/*
              Keluar wajib POST: sebagai tautan GET ia dapat dipicu oleh
              prefetch peramban atau tag <img> di situs lain, membuat operator
              terlempar keluar tanpa pernah menekan apa pun.
            */}
            <button
              onClick={() => router.post('/admin/keluar')}
              className="shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50"
            >
              Keluar
            </button>
          </div>
        </header>

        <main className="flex-1 p-6">{children}</main>
      </div>
    </div>
  )
}
