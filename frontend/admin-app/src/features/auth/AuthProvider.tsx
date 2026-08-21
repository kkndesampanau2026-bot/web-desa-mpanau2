import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { ambilCsrfCookie, api, ApiRequestError, getData } from '@/lib/api'
import { AuthContext, type AuthContextValue } from './AuthContext'
import type { AuthUser, LoginCredentials } from './types'

/**
 * Menyediakan sesi operator ke seluruh Admin App.
 *
 * Sesi disimpan pada cookie httpOnly milik Sanctum, bukan di localStorage,
 * sehingga status login tidak dapat dibaca dari JavaScript. Konsekuensinya
 * saat aplikasi dimuat kita harus bertanya ke server ("siapa saya?") alih-alih
 * membaca state lokal — itulah fungsi pemulihan sesi di bawah.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [sedangMemuat, setSedangMemuat] = useState(true)

  useEffect(() => {
    let dibatalkan = false

    async function pulihkanSesi() {
      try {
        const profil = await getData<AuthUser>('/auth/me')
        if (!dibatalkan) setUser(profil)
      } catch (error) {
        // 401 di sini adalah keadaan normal (pengunjung belum login),
        // bukan kegagalan — jadi tidak perlu dilaporkan sebagai galat.
        if (!dibatalkan && !(error instanceof ApiRequestError && error.status === 401)) {
          console.error('Gagal memulihkan sesi:', error)
        }
      } finally {
        if (!dibatalkan) setSedangMemuat(false)
      }
    }

    void pulihkanSesi()

    return () => {
      dibatalkan = true
    }
  }, [])

  const login = useCallback(async (credentials: LoginCredentials) => {
    await ambilCsrfCookie()
    const { data } = await api.post('/auth/login', credentials)
    setUser(data.data as AuthUser)
  }, [])

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout')
    } finally {
      // State lokal selalu dibersihkan, bahkan bila permintaan gagal —
      // menahan pengguna di layar terautentikasi setelah ia menekan keluar
      // adalah kegagalan yang lebih buruk daripada sesi server yang tertinggal.
      setUser(null)
    }
  }, [])

  const punyaIzin = useCallback(
    (permission: string) => {
      if (!user) return false

      // Super Admin sengaja tidak memiliki permission eksplisit — aksesnya
      // ditangani Gate::before di server. Tanpa pengecualian ini, ia justru
      // akan terkunci dari seluruh menu meski server mengizinkannya.
      if (user.roles.includes('Super Admin')) return true

      return user.permissions.includes(permission)
    },
    [user],
  )

  const value = useMemo<AuthContextValue>(
    () => ({ user, sedangMemuat, login, logout, punyaIzin }),
    [user, sedangMemuat, login, logout, punyaIzin],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
