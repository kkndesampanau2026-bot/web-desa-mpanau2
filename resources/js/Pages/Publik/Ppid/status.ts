import type { StatusPermohonanPpid } from '@/types/api'

/**
 * Label dan warna status permohonan PPID.
 *
 * Dipakai halaman tanda terima maupun halaman pelacakan; dulu keduanya berada
 * di satu berkas sehingga konstanta ini cukup ditulis sekali. Setelah tiap
 * halaman Inertia berdiri sendiri, keduanya diangkat ke sini agar tidak
 * berakhir sebagai dua daftar yang bisa berbeda isi.
 */
export const LABEL_STATUS_PPID: Record<StatusPermohonanPpid['status'], string> = {
  diajukan: 'Diajukan',
  diverifikasi: 'Diverifikasi',
  diproses: 'Sedang Diproses',
  selesai: 'Selesai',
  ditolak: 'Ditolak',
}

export const GAYA_STATUS_PPID: Record<
  StatusPermohonanPpid['status'],
  'netral' | 'kuning' | 'hijau' | 'merah'
> = {
  diajukan: 'netral',
  diverifikasi: 'kuning',
  diproses: 'kuning',
  selesai: 'hijau',
  ditolak: 'merah',
}
