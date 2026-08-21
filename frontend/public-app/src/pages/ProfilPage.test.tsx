import { describe, expect, it, vi, beforeEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { renderDenganProvider } from '@/test/render'
import { ProfilPage } from './ProfilPage'
import { api } from '@/lib/api'
import type { Profil } from '@/types/api'

/**
 * Smoke test halaman Profil Desa — memastikan komponen benar-benar merender
 * data dari API dan menghormati pola empty-state PRD 3.2.
 */

const PROFIL_CONTOH: Profil = {
  sambutan: '<p>Selamat datang di Desa Mpanau.</p>',
  nama_kepala_desa: 'Budi Santoso',
  foto_kepala_desa: null,
  sejarah: null,
  visi: 'Desa mandiri dan sejahtera',
  misi: ['Meningkatkan pelayanan publik', 'Mengembangkan ekonomi desa'],
  bagan_pemerintahan: null,
  bagan_bpd: null,
  geografis: {
    luas_desa_m2: 2_512_500,
    luas_desa_hektar: 251.25,
    jumlah_penduduk: 3200,
    batas: { utara: 'Desa Sidera', timur: null, selatan: null, barat: null },
  },
  peta: { latitude: null, longitude: null },
}

/** Membuat balasan bergaya ApiResponse untuk tiap endpoint yang dipanggil. */
function pasangApi(profil: Profil | null) {
  vi.spyOn(api, 'get').mockImplementation((url: string) => {
    const data =
      url === '/profil' ? profil
        : url === '/officials' ? [{ id: 1, nama: 'Budi Santoso', jabatan: 'Kepala Desa', foto: null, periode_mulai: null, periode_selesai: null, urutan_tampil: 1 }]
        : []

    return Promise.resolve({ data: { success: true, message: null, data } }) as never
  })
}

describe('ProfilPage', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('menampilkan visi, misi, dan data geografis dari API', async () => {
    pasangApi(PROFIL_CONTOH)

    renderDenganProvider(<ProfilPage />)

    expect(await screen.findByText('Desa mandiri dan sejahtera')).toBeInTheDocument()
    expect(screen.getByText('Meningkatkan pelayanan publik')).toBeInTheDocument()
    expect(screen.getByText('Mengembangkan ekonomi desa')).toBeInTheDocument()

    // Luas ditampilkan dalam hektar dengan pemisah ribuan Indonesia.
    expect(screen.getByText('251,25 ha')).toBeInTheDocument()
    expect(screen.getByText('Desa Sidera')).toBeInTheDocument()
  })

  it('merender sambutan rich text sebagai HTML, bukan teks mentah', async () => {
    pasangApi(PROFIL_CONTOH)

    renderDenganProvider(<ProfilPage />)

    // Bila HTML tidak dirender, tag <p> akan ikut terbaca sebagai teks.
    expect(await screen.findByText('Selamat datang di Desa Mpanau.')).toBeInTheDocument()
    expect(screen.queryByText(/<p>/)).not.toBeInTheDocument()
  })

  /** Pola empty-state PRD 3.2: bukan halaman kosong atau error. */
  it('menampilkan empty-state informatif saat profil belum diisi', async () => {
    pasangApi(null)

    renderDenganProvider(<ProfilPage />)

    expect(await screen.findByText('Belum Ada Data')).toBeInTheDocument()
    // Judul modul & penjelasan fungsinya tetap tampil.
    expect(screen.getByText('Profil Desa')).toBeInTheDocument()
  })

  it('menampilkan pesan gagal muat ketika API bermasalah', async () => {
    vi.spyOn(api, 'get').mockRejectedValue(new Error('jaringan putus'))

    renderDenganProvider(<ProfilPage />)

    await waitFor(() => {
      expect(screen.getByText('Gagal Memuat Data')).toBeInTheDocument()
    })
  })
})
