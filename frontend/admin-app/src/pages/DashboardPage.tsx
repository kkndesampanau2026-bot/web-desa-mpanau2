import { useAuth } from '@/features/auth/AuthContext'

/**
 * Placeholder Fase 1. Isi sesungguhnya (ringkasan visitor, pengaduan &
 * permohonan PPID masuk, konten terbaru, grafik tren) menyusul pada Fase 2
 * bersama modul Statistik Kunjungan — PRD 5.20.
 */
export function DashboardPage() {
  const { user, logout } = useAuth()

  return (
    <div className="min-h-screen bg-slate-100">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
          <div>
            <h1 className="font-semibold text-slate-900">Dashboard Admin</h1>
            <p className="text-sm text-slate-500">
              {user?.nama} — {user?.roles.join(', ')}
            </p>
          </div>
          <button
            onClick={() => void logout()}
            className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50"
          >
            Keluar
          </button>
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-6 py-8">
        <div className="rounded-xl border border-slate-200 bg-white p-6">
          <h2 className="font-medium text-slate-900">Fondasi Fase 1 aktif</h2>
          <p className="mt-1 text-sm text-slate-600">
            Autentikasi Sanctum dan otorisasi berbasis permission sudah berjalan.
            Modul CMS ditambahkan bertahap mulai Fase 2.
          </p>

          <h3 className="mt-6 text-sm font-medium text-slate-900">
            Izin akun ini ({user?.permissions.length ?? 0})
          </h3>
          <ul className="mt-2 flex flex-wrap gap-1.5">
            {user?.permissions.map((izin) => (
              <li
                key={izin}
                className="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700"
              >
                {izin}
              </li>
            ))}
            {user?.permissions.length === 0 && (
              <li className="text-sm text-slate-500">
                Tidak ada permission eksplisit (akses Super Admin lewat Gate).
              </li>
            )}
          </ul>
        </div>
      </main>
    </div>
  )
}
