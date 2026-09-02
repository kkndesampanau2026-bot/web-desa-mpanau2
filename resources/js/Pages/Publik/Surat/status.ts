import type { AksiRiwayatSurat, StatusSurat } from '@/types/api'

/**
 * Label & warna status Surat Pengantar.
 *
 * Basis data menyimpan status dalam bentuk mesin (MENUNGGU_APPROVAL_RT);
 * kalimat yang dibaca warga hidup di berkas ini. Pemisahan itu yang membuat
 * bahasanya dapat diperhalus kapan saja tanpa migrasi — dan mengikuti pola
 * yang sudah dipakai modul Pengaduan & PPID.
 */
export const LABEL_STATUS_SURAT: Record<StatusSurat, string> = {
  MENUNGGU_APPROVAL_RT: 'Menunggu Persetujuan Ketua RT',
  MENUNGGU_APPROVAL_KADUS: 'Menunggu Persetujuan Kepala Dusun',
  DISETUJUI: 'Surat Disetujui',
  DITOLAK: 'Pengajuan Ditolak',
}

/** Kalimat penjelas di bawah lencana status. */
export const PENJELASAN_STATUS_SURAT: Record<StatusSurat, string> = {
  MENUNGGU_APPROVAL_RT: 'Pengajuan sedang diperiksa oleh Ketua RT.',
  MENUNGGU_APPROVAL_KADUS:
    'Pengajuan telah disetujui Ketua RT dan sedang diperiksa Kepala Dusun.',
  DISETUJUI: 'Surat telah disetujui dan dapat diunduh.',
  DITOLAK: 'Pengajuan ditolak. Silakan baca alasannya di bawah.',
}

export const GAYA_STATUS_SURAT: Record<StatusSurat, 'kuning' | 'hijau' | 'merah'> = {
  MENUNGGU_APPROVAL_RT: 'kuning',
  MENUNGGU_APPROVAL_KADUS: 'kuning',
  DISETUJUI: 'hijau',
  DITOLAK: 'merah',
}

/**
 * Lini masa baku empat tahap.
 *
 * Ditulis sebagai daftar tetap, bukan disusun dari riwayat yang diterima,
 * supaya warga melihat tahap yang BELUM terjadi juga — itulah yang menjawab
 * pertanyaan "masih berapa lama lagi". Riwayat dari server hanya mengisi
 * waktunya.
 */
export interface TahapSurat {
  kunci: string
  label: string
  /** Aksi pada riwayat server yang menandai tahap ini selesai. */
  ditandai: AksiRiwayatSurat[]
}

export const TAHAP_SURAT: TahapSurat[] = [
  { kunci: 'diajukan', label: 'Pengajuan dibuat', ditandai: ['diajukan'] },
  { kunci: 'rt', label: 'Disetujui Ketua RT', ditandai: ['disetujui_rt'] },
  { kunci: 'kadus', label: 'Disetujui Kepala Dusun', ditandai: ['disetujui_kadus'] },
  { kunci: 'terbit', label: 'Surat selesai', ditandai: ['surat_terbit'] },
]
