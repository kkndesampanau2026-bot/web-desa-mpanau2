import axios, { AxiosError } from 'axios'

/**
 * Klien HTTP layar CMS — pasangan sisi klien dari kontrak
 * `App\Support\ApiResponse` (PRD Bagian 9).
 *
 * SEMENTARA. Halaman publik sudah menerima datanya sebagai prop Inertia,
 * sementara layar CMS masih memanggil `/api/v1/admin/*` lewat XHR. Klien ini
 * hidup sampai modul terakhir berpindah, lalu ikut dihapus bersama
 * `routes/api.php` — lihat Fase 4–5 pada `docs/MIGRASI-MONOLIT.md`.
 *
 * Basis URL sengaja kosong: sejak dashboard disajikan Laravel sendiri, API
 * berada pada origin yang sama. Menuliskan host secara eksplisit (dulu
 * `http://localhost:8000`) justru akan menghidupkan kembali permintaan lintas
 * origin beserta CORS-nya.
 */

const BASE_URL = ''

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
