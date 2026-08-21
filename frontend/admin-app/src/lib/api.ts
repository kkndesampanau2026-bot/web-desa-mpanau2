import axios, { AxiosError } from 'axios'

/**
 * Klien HTTP Admin App — pasangan sisi klien dari kontrak
 * `App\Support\ApiResponse` pada backend (PRD Bagian 9).
 *
 * Memakai Sanctum SPA (cookie httpOnly), bukan token bearer di localStorage,
 * sehingga `withCredentials` wajib true dan setiap permintaan yang mengubah
 * data harus didahului pengambilan cookie CSRF.
 */

const BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

/**
 * URL berkas publik hasil unggahan.
 *
 * Backend menyimpan *path relatif* (mis. `berita/uuid.webp`), bukan URL penuh,
 * supaya basis data tetap sahih ketika domain situs berubah. Penyusunan URL
 * karenanya menjadi urusan sisi klien.
 */
export function urlBerkas(path: string | null | undefined): string | null {
  if (!path) return null
  return `${BASE_URL}/storage/${path}`
}

export const api = axios.create({
  baseURL: `${BASE_URL}/api/v1`,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/** Bentuk response sukses dari backend. */
export interface ApiSuccess<T> {
  success: true
  message: string | null
  data: T
  meta?: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

/** Bentuk response galat dari backend. */
export interface ApiError {
  success: false
  message: string
  errors?: Record<string, string[]>
}

/**
 * Galat API yang sudah dinormalkan, agar komponen tidak perlu membongkar
 * struktur AxiosError untuk menampilkan pesan atau galat per-field.
 */
export class ApiRequestError extends Error {
  // Ditulis sebagai field eksplisit, bukan parameter property, karena
  // tsconfig mengaktifkan `erasableSyntaxOnly` — sintaks TS yang menghasilkan
  // kode saat runtime tidak diizinkan.
  readonly status: number
  readonly errors?: Record<string, string[]>

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message)
    this.name = 'ApiRequestError'
    this.status = status
    this.errors = errors
  }

  /** Pesan validasi pertama untuk sebuah field, jika ada. */
  fieldError(field: string): string | undefined {
    return this.errors?.[field]?.[0]
  }
}

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    // Galat jaringan / server tak terjangkau: tidak ada payload kontrak.
    if (!error.response) {
      return Promise.reject(
        new ApiRequestError('Tidak dapat terhubung ke server. Periksa koneksi Anda.', 0),
      )
    }

    const { status, data } = error.response

    return Promise.reject(
      new ApiRequestError(
        data?.message ?? 'Terjadi kesalahan pada server.',
        status,
        data?.errors,
      ),
    )
  },
)

/**
 * Mengambil cookie CSRF Sanctum. Wajib dipanggil sekali sebelum permintaan
 * POST/PUT/DELETE pertama dalam sebuah sesi browser.
 */
export async function ambilCsrfCookie(): Promise<void> {
  await axios.get(`${BASE_URL}/sanctum/csrf-cookie`, { withCredentials: true })
}

/** Membuka pembungkus response dan mengembalikan `data` saja. */
export async function getData<T>(url: string, params?: unknown): Promise<T> {
  const response = await api.get<ApiSuccess<T>>(url, { params })
  return response.data.data
}
