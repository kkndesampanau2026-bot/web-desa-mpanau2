import { createContext, useContext } from 'react'
import type { AuthUser, LoginCredentials } from './types'

export interface AuthContextValue {
  user: AuthUser | null
  /** true selama pemulihan sesi awal — bedakan dari "sudah pasti tamu". */
  sedangMemuat: boolean
  login: (credentials: LoginCredentials) => Promise<void>
  logout: () => Promise<void>
  /** Cek izin untuk keperluan tampilan saja (lihat catatan pada AuthUser). */
  punyaIzin: (permission: string) => boolean
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth harus dipakai di dalam <AuthProvider>.')
  }

  return context
}
