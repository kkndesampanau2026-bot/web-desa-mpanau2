import { usePage } from '@inertiajs/react'
import type { MetaPaginasi, Pengaturan, StatistikKunjungan } from './api'

/**
 * Bentuk prop daftar berhalaman — padanan `App\Support\Paginasi::bentuk()`.
 */
export interface Berhalaman<T> {
  items: T[]
  meta: MetaPaginasi
}

/**
 * Prop yang dikirim `HandleInertiaRequests::share()` bersama SETIAP respons.
 *
 * Padanan sisi klien dari middleware tersebut — kalau salah satu berubah,
 * yang lain harus ikut. Sebelumnya data ini diambil lewat permintaan HTTP
 * terpisah (`/auth/me`, `/settings`, `/visitor-stats`); kini ia menumpang
 * pada respons halaman.
 */

/** Profil operator yang sedang masuk. */
export interface PenggunaAuth {
  id: number
  nama: string
  email: string
  no_telepon: string | null
  village_id: number | null
  roles: string[]
  /**
   * Dipakai hanya untuk menyembunyikan menu/tombol yang tidak relevan.
   * Otorisasi sesungguhnya tetap ditegakkan server pada setiap route —
   * jangan pernah memperlakukan daftar ini sebagai batas keamanan.
   */
  permissions: string[]
}

/** Satu baris pratinjau pada dropdown lonceng notifikasi. */
export interface NotifikasiItem {
  judul: string
  ringkas: string
  /** Sudah berbentuk teks relatif ("5 menit yang lalu"), bukan tanggal mentah. */
  waktu: string
  ke: string
}

/** Antrean satu layanan warga. */
export interface NotifikasiGrup {
  kunci: 'pengaduan' | 'ppid' | 'surat'
  label: string
  /** Menjelaskan APA yang ditunggu, mis. "Menunggu diverifikasi". */
  keterangan: string
  /** Halaman layanannya, untuk tautan "Lihat semua". */
  ke: string
  jumlah: number
  /** Maksimal 5 teratas — pratinjau, bukan daftar kerja. */
  item: NotifikasiItem[]
}

export interface Notifikasi {
  /** Jumlah SELURUH grup yang boleh dilihat operator ini. */
  jumlah: number
  grup: NotifikasiGrup[]
}

export interface PropsBersama {
  auth: { user: PenggunaAuth | null }
  /** Null pada halaman dashboard: kerangka CMS tidak memakai header situs. */
  pengaturan: Pengaturan | null
  statistik_kunjungan: StatistikKunjungan | null
  /**
   * Kategori pengaduan untuk formulir "Aduan Warga" yang dapat dibuka dari
   * tombol mengambang di halaman mana pun. Null pada dashboard CMS.
   */
  kategori_pengaduan: string[] | null
  /**
   * Untuk lonceng notifikasi di `LayoutAdmin`. Null di luar dashboard CMS
   * atau bila operator tidak berwenang atas SATU pun layanan warga.
   */
  notifikasi: Notifikasi | null
  flash: { sukses: string | null; galat: string | null }
  errors: Record<string, string>
  [key: string]: unknown
}

/** `usePage()` yang sudah bertipe — supaya prop bersama tidak perlu di-cast. */
export function useHalaman() {
  return usePage<PropsBersama>()
}

/**
 * Cek izin untuk keperluan tampilan saja.
 *
 * Super Admin sengaja tidak memiliki permission eksplisit — aksesnya
 * ditangani `Gate::before` di server. Tanpa pengecualian di bawah, ia justru
 * akan terkunci dari seluruh menu meski server mengizinkannya.
 */
export function punyaIzin(pengguna: PenggunaAuth | null, izin: string): boolean {
  if (!pengguna) return false
  if (pengguna.roles.includes('Super Admin')) return true

  return pengguna.permissions.includes(izin)
}
