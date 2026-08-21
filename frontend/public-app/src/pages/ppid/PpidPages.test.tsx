import { describe, expect, it, vi, beforeEach } from 'vitest'
import { screen, within } from '@testing-library/react'
import { renderDenganProvider } from '@/test/render'
import { DasarHukumPage, InformasiPpidPage, PpidBerandaPage } from './PpidPages'
import { api } from '@/lib/api'
import type { DasarHukumPpid, InformasiPpid } from '@/types/api'

/**
 * Smoke test halaman PPID setelah pembaruan tampilan.
 *
 * Fokusnya memastikan redesign TIDAK merusak apa pun yang sudah berjalan:
 * data tetap terbaca, tautan tetap ada, dan struktur heading tetap benar.
 */

const DASAR_HUKUM: DasarHukumPpid[] = [
  {
    judul_regulasi: 'Undang-Undang Keterbukaan Informasi Publik',
    nomor_regulasi: 'Nomor 14 Tahun 2008',
    tahun: 2008,
    file: null,
  },
  {
    judul_regulasi: 'Undang-Undang Desa',
    nomor_regulasi: 'Nomor 6 Tahun 2014',
    tahun: 2014,
    file: 'https://contoh.test/uu-desa.pdf',
  },
]

const INFORMASI: InformasiPpid[] = [
  {
    judul: 'Laporan Realisasi APBDes Semester I 2026',
    deskripsi: null,
    kategori: 'Laporan Keuangan',
    periode: 'Semester I 2026',
    tingkat_urgensi: null,
    tanggal_publish: '2026-07-01',
    file: 'https://contoh.test/apbdes.pdf',
  },
]

function pasangApi(data: unknown) {
  vi.spyOn(api, 'get').mockResolvedValue({
    data: { success: true, message: null, data },
  } as never)
}

describe('Halaman PPID', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('beranda PPID menampilkan tiga kategori informasi dan CTA permohonan', () => {
    renderDenganProvider(<PpidBerandaPage />)

    expect(
      screen.getByRole('heading', { name: /Pejabat Pengelola Informasi/i }),
    ).toBeInTheDocument()

    // Tiga kategori baku UU KIP harus semuanya tampil.
    expect(screen.getByText('Informasi Berkala')).toBeInTheDocument()
    expect(screen.getByText('Informasi Serta-Merta')).toBeInTheDocument()
    expect(screen.getByText('Informasi Setiap Saat')).toBeInTheDocument()

    // Jalur utama halaman ini: mengajukan permohonan.
    const cta = screen.getAllByRole('link', { name: /Ajukan Permohonan/i })
    expect(cta.length).toBeGreaterThan(0)
    expect(cta[0]).toHaveAttribute('href', '/ppid/permintaan')
  })

  it('dasar hukum menampilkan regulasi beserta tautan unduhnya', async () => {
    pasangApi(DASAR_HUKUM)

    renderDenganProvider(<DasarHukumPage />)

    expect(
      await screen.findByText('Undang-Undang Keterbukaan Informasi Publik'),
    ).toBeInTheDocument()
    expect(screen.getByText(/Nomor 6 Tahun 2014/)).toBeInTheDocument()

    // Hanya regulasi yang punya berkas yang menampilkan tautan unduh.
    const unduh = screen.getAllByRole('link', { name: /Unduh dokumen/i })
    expect(unduh).toHaveLength(1)
    expect(unduh[0]).toHaveAttribute('href', 'https://contoh.test/uu-desa.pdf')
  })

  it('daftar informasi menampilkan metadata dokumen', async () => {
    pasangApi(INFORMASI)

    renderDenganProvider(<InformasiPpidPage jenis="berkala" />)

    expect(
      await screen.findByText('Laporan Realisasi APBDes Semester I 2026'),
    ).toBeInTheDocument()
    expect(screen.getByText('Laporan Keuangan')).toBeInTheDocument()
    expect(screen.getByText('Semester I 2026')).toBeInTheDocument()

    // Tanggal diformat gaya Indonesia, bukan ISO mentah.
    expect(screen.getByText(/1 Juli 2026/)).toBeInTheDocument()
  })

  /** Pola empty-state PRD 3.2 harus bertahan setelah redesign. */
  it('menampilkan empty-state saat belum ada dokumen', async () => {
    pasangApi([])

    renderDenganProvider(<InformasiPpidPage jenis="setiap-saat" />)

    expect(await screen.findByText('Belum Ada Data')).toBeInTheDocument()
    expect(
      screen.getByRole('heading', { name: 'Informasi Setiap Saat' }),
    ).toBeInTheDocument()
  })

  it('hanya ada satu heading tingkat satu pada tiap halaman', async () => {
    pasangApi(DASAR_HUKUM)

    const { container } = renderDenganProvider(<DasarHukumPage />)
    await screen.findByText('Undang-Undang Desa')

    // Hierarki heading yang benar penting bagi pembaca layar (PRD 12.4).
    expect(within(container).getAllByRole('heading', { level: 1 })).toHaveLength(1)
  })
})
