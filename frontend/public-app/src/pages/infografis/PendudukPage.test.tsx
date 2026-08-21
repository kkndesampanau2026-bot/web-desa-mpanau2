import { describe, expect, it, vi, beforeEach } from 'vitest'
import { screen } from '@testing-library/react'
import { renderDenganProvider } from '@/test/render'
import { PendudukPage } from './PendudukPage'
import { api } from '@/lib/api'
import type { InfografisPenduduk } from '@/types/api'

/**
 * Smoke test Infografis Kependudukan.
 *
 * Dua hal yang dijaga: angka terformat benar untuk pembaca Indonesia, dan
 * setiap grafik menyertakan tabel setara bagi pembaca layar (PRD 12.4).
 */

const DATA: InfografisPenduduk = {
  periode: '2026-08',
  ringkasan: {
    total_penduduk: 1250,
    total_kk: 400,
    total_laki: 640,
    total_perempuan: 610,
    total_wajib_pilih: 890,
  },
  breakdown: {
    kelompok_umur: [
      { label: 'Balita (0-4)', jumlah: 95 },
      { label: 'Dewasa (18-59)', jumlah: 700 },
    ],
    dusun: [{ label: 'Dusun 1', jumlah: 420 }],
    pendidikan: [{ label: 'SLTA/Sederajat', jumlah: 310 }],
    pekerjaan: [{ label: 'Petani', jumlah: 280 }],
    perkawinan: [{ label: 'Kawin', jumlah: 600 }],
    agama: [{ label: 'Islam', jumlah: 1100 }],
  },
  riwayat: [
    { periode: '2026-07', total_penduduk: 1240, total_kk: 398 },
    { periode: '2026-08', total_penduduk: 1250, total_kk: 400 },
  ],
}

function pasangApi(data: InfografisPenduduk | null) {
  vi.spyOn(api, 'get').mockResolvedValue({
    data: { success: true, message: null, data },
  } as never)
}

describe('PendudukPage', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('menampilkan ringkasan kependudukan dengan format angka Indonesia', async () => {
    pasangApi(DATA)

    renderDenganProvider(<PendudukPage />)

    // 1250 → "1.250" (pemisah ribuan titik, bukan koma).
    expect(await screen.findByText('1.250')).toBeInTheDocument()
    expect(screen.getByText('400')).toBeInTheDocument()
    expect(screen.getByText('890')).toBeInTheDocument()
  })

  it('menghitung rata-rata jiwa per KK', async () => {
    pasangApi(DATA)

    renderDenganProvider(<PendudukPage />)

    // 1250 / 400 = 3,1
    expect(await screen.findByText('3.1')).toBeInTheDocument()
  })

  /**
   * Grafik dirender sebagai SVG yang tidak terbaca pembaca layar, sehingga
   * tabel setara wajib ada — bila hilang, datanya hanya tersampaikan secara
   * visual.
   */
  it('menyertakan tabel setara untuk setiap grafik', async () => {
    pasangApi(DATA)

    renderDenganProvider(<PendudukPage />)

    // Judul grafik muncul dua kali — sebagai figcaption DAN sebagai caption
    // tabel setara. Kehadiran keduanya justru yang sedang diuji di sini.
    expect((await screen.findAllByText('Kelompok Umur')).length).toBe(2)

    const tabel = screen.getAllByRole('table', { hidden: false })
    expect(tabel.length).toBeGreaterThan(0)

    // Nilai dari breakdown terbaca sebagai teks, bukan hanya tergambar.
    expect(screen.getAllByText('Petani').length).toBeGreaterThan(0)
  })

  it('menampilkan empty-state saat data belum tersedia', async () => {
    pasangApi(null)

    renderDenganProvider(<PendudukPage />)

    expect(await screen.findByText('Belum Ada Data')).toBeInTheDocument()
    expect(screen.getByText('Infografis Kependudukan')).toBeInTheDocument()
  })
})
