import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from './AuthContext'

/**
 * Menjaga seluruh route di balik autentikasi.
 *
 * Selama sesi masih dipulihkan kita menahan render, bukan langsung mengalihkan
 * ke login — tanpa jeda ini pengguna yang sesinya masih sah akan terlempar
 * keluar setiap kali menyegarkan halaman.
 */
export function RouteTerproteksi({ izinDibutuhkan }: { izinDibutuhkan?: string }) {
  const { user, sedangMemuat, punyaIzin } = useAuth()
  const location = useLocation()

  if (sedangMemuat) {
    return (
      <div className="flex min-h-screen items-center justify-center text-slate-500">
        Memuat sesi…
      </div>
    )
  }

  if (!user) {
    // `state` menyimpan tujuan semula agar pengguna dikembalikan ke sana
    // setelah berhasil masuk.
    return <Navigate to="/login" replace state={{ dari: location.pathname }} />
  }

  if (izinDibutuhkan && !punyaIzin(izinDibutuhkan)) {
    return <Navigate to="/tidak-berwenang" replace />
  }

  return <Outlet />
}
