/**
 * Utilitas unggahan berkas — pasangan sisi klien dari `App\Services\MediaService`.
 *
 * Batas dan daftar tipe di bawah SENGAJA menduplikasi aturan backend. Ini bukan
 * lapisan keamanan — pemeriksaan di browser sepenuhnya dapat dilewati, dan
 * backend tetap memvalidasi ulang setiap berkas. Gunanya semata memberi tahu
 * operator desa sebelum ia menunggu unggahan 8 MB selesai hanya untuk ditolak.
 */

export const TIPE_GAMBAR = ['image/jpeg', 'image/png', 'image/webp'] as const
export const TIPE_DOKUMEN = ['application/pdf'] as const

export const MAKS_GAMBAR_MB = 5
export const MAKS_DOKUMEN_MB = 10

/** Berapa foto yang boleh dikirim dalam satu permintaan (lihat MediaService). */
export const MAKS_FOTO_SEKALI_UNGGAH = 10

export type JenisBerkas = 'gambar' | 'dokumen'

const ATURAN = {
  gambar: {
    tipe: TIPE_GAMBAR as readonly string[],
    maksMb: MAKS_GAMBAR_MB,
    accept: 'image/jpeg,image/png,image/webp',
    sebutan: 'Gambar',
    format: 'JPG, PNG, atau WebP',
  },
  dokumen: {
    tipe: TIPE_DOKUMEN as readonly string[],
    maksMb: MAKS_DOKUMEN_MB,
    accept: 'application/pdf',
    sebutan: 'Dokumen',
    format: 'PDF',
  },
} satisfies Record<JenisBerkas, unknown>

export function aturanBerkas(jenis: JenisBerkas) {
  return ATURAN[jenis]
}

/** Ukuran berkas dalam satuan yang enak dibaca operator. */
export function ukuranTerbaca(byte: number): string {
  if (byte < 1024) return `${byte} B`
  if (byte < 1024 * 1024) return `${Math.round(byte / 1024)} KB`
  return `${(byte / (1024 * 1024)).toFixed(1)} MB`
}

/**
 * Memeriksa satu berkas terhadap aturan jenisnya.
 *
 * @returns pesan galat, atau `null` bila berkas lolos.
 */
export function periksaBerkas(berkas: File, jenis: JenisBerkas): string | null {
  const { tipe, maksMb, sebutan, format } = ATURAN[jenis]

  // Tipe dibaca dari berkasnya, bukan dari ekstensi nama: atribut `accept`
  // hanya menyaring dialog pemilih dan mudah dilewati dengan seret-lepas.
  if (!tipe.includes(berkas.type)) {
    return `${sebutan} harus berformat ${format}.`
  }

  if (berkas.size > maksMb * 1024 * 1024) {
    return `Ukuran berkas ${ukuranTerbaca(berkas.size)} melampaui batas ${maksMb} MB.`
  }

  if (berkas.size === 0) {
    return 'Berkas kosong atau gagal dibaca.'
  }

  return null
}

/**
 * Menyusun `FormData` dari objek biasa.
 *
 * Diperlukan karena berkas tidak dapat dikirim sebagai JSON. Aturan konversi:
 *
 * - `undefined` dan `null` DILEWATI — bukan dikirim sebagai string "null".
 *   Kolom yang dilewati tidak akan ikut diperbarui backend, yang persis
 *   perilaku yang diinginkan untuk input berkas yang tidak diubah operator.
 * - `boolean` menjadi "1"/"0", satu-satunya bentuk yang dikenali aturan
 *   validasi `boolean` Laravel.
 * - String kosong TETAP dikirim: middleware `ConvertEmptyStringsToNull`
 *   mengubahnya menjadi null di sisi server, sehingga operator dapat
 *   mengosongkan kolom teks yang sebelumnya terisi.
 * - Array dan objek bersarang dijabarkan menjadi `kunci[0][sub]`, bentuk yang
 *   diurai Laravel kembali menjadi array.
 */
export function keFormData(
  nilai: Record<string, unknown>,
  opsi: { method?: 'PUT' | 'PATCH' | 'DELETE' } = {},
): FormData {
  const data = new FormData()

  // PHP tidak mengurai body multipart pada request PUT/PATCH, sehingga
  // permintaan dikirim sebagai POST dan Laravel memulihkan method aslinya dari
  // field ini. Tanpa penyiasatan ini, seluruh formulir tiba dalam keadaan
  // kosong di sisi server.
  if (opsi.method) data.append('_method', opsi.method)

  for (const [kunci, isi] of Object.entries(nilai)) {
    tambah(data, kunci, isi)
  }

  return data
}

function tambah(data: FormData, kunci: string, isi: unknown): void {
  if (isi === undefined || isi === null) return

  if (isi instanceof File || isi instanceof Blob) {
    data.append(kunci, isi)
    return
  }

  if (typeof isi === 'boolean') {
    data.append(kunci, isi ? '1' : '0')
    return
  }

  if (Array.isArray(isi)) {
    // Daftar kosong dikirim sebagai string kosong, bukan dilewati begitu saja.
    // Server mengubahnya menjadi null (`nullable|array` menerimanya), sehingga
    // operator tetap bisa MENGOSONGKAN daftar yang sebelumnya terisi. Bila
    // kuncinya dilewati, backend menganggap kolomnya tidak disertakan dan isi
    // lama bertahan — daftar menjadi mustahil dikosongkan.
    //
    // Mengirim `kunci[]=''` tidak bisa dipakai: hasilnya array berisi satu
    // elemen null, yang justru gagal pada aturan `kunci.*`.
    if (isi.length === 0) {
      data.append(kunci, '')
      return
    }

    isi.forEach((item, i) => tambah(data, `${kunci}[${i}]`, item))
    return
  }

  if (typeof isi === 'object') {
    for (const [sub, nilaiSub] of Object.entries(isi as Record<string, unknown>)) {
      tambah(data, `${kunci}[${sub}]`, nilaiSub)
    }
    return
  }

  data.append(kunci, String(isi))
}
