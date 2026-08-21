/** Profil operator yang login — cermin dari AuthController::profil(). */
export interface AuthUser {
  id: number
  nama: string
  email: string
  no_telepon: string | null
  village_id: number | null
  roles: string[]
  /**
   * Dipakai hanya untuk menyembunyikan menu/tombol yang tidak relevan.
   * Otorisasi sesungguhnya tetap ditegakkan server pada setiap endpoint —
   * jangan pernah memperlakukan daftar ini sebagai batas keamanan.
   */
  permissions: string[]
}

export interface LoginCredentials {
  email: string
  password: string
  remember?: boolean
}
