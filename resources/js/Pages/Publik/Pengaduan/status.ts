import type { StatusPengaduan } from '@/types/api'

/**
 * Label status pengaduan, dipakai halaman tanda terima maupun pelacakan.
 *
 * Dulu keduanya berada di satu berkas sehingga konstanta ini cukup ditulis
 * sekali. Setelah tiap halaman Inertia berdiri sendiri, ia diangkat ke sini
 * agar tidak berakhir sebagai dua daftar yang bisa berbeda isi.
 */
export const LABEL_STATUS_PENGADUAN: Record<StatusPengaduan['status'], string> = {
  baru: 'Baru',
  diproses: 'Sedang Diproses',
  selesai: 'Selesai',
  ditolak: 'Ditolak',
}

export const GAYA_INPUT_PENGADUAN =
  'mt-1 w-full rounded-lg border border-navy/20 px-3 py-2 text-sm outline-none focus:border-navy'
