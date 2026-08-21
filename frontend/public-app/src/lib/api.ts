import axios, { AxiosError } from 'axios'

/**
 * Klien HTTP Public App — pasangan sisi klien dari kontrak
 * `App\Support\ApiResponse` pada backend (PRD Bagian 9).
 *
 * Berbeda dari Admin App, klien ini TIDAK mengirim kredensial: seluruh
 * layanan publik (berita, infografis, pengaduan, cek bansos, permohonan PPID)
 * dirancang tanpa login — lihat PRD 6.14–6.15. Menyalakan withCredentials di
 * sini hanya akan memperluas permukaan serangan CSRF tanpa manfaat.
 */

const BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

export const api = axios.create({
  baseURL: `${BASE_URL}/api/v1`,
  headers: {
    Accept: 'application/json',
  },
})

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

export interface ApiError {
  success: false
  message: string
  errors?: Record<string, string[]>
}

export class ApiRequestError extends Error {
  // Field eksplisit, bukan parameter property: tsconfig mengaktifkan
  // `erasableSyntaxOnly`.
  readonly status: number
  readonly errors?: Record<string, string[]>

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message)
    this.name = 'ApiRequestError'
    this.status = status
    this.errors = errors
  }

  fieldError(field: string): string | undefined {
    return this.errors?.[field]?.[0]
  }
}

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    if (!error.response) {
      return Promise.reject(
        new ApiRequestError('Tidak dapat terhubung ke server. Periksa koneksi Anda.', 0),
      )
    }

    const { status, data } = error.response

    return Promise.reject(
      new ApiRequestError(data?.message ?? 'Terjadi kesalahan pada server.', status, data?.errors),
    )
  },
)

/** Membuka pembungkus response dan mengembalikan `data` saja. */
export async function getData<T>(url: string, params?: unknown): Promise<T> {
  const response = await api.get<ApiSuccess<T>>(url, { params })
  return response.data.data
}
