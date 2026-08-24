import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { IsiHalaman, Kartu } from '@/Components/ui'

/**
 * Halaman galat — 404, 403, 419, 500.
 *
 * Sebelumnya alamat yang salah berakhir di halaman galat bawaan Laravel:
 * layar putih berbahasa Inggris tanpa jalan kembali, dan tanpa identitas desa
 * sama sekali. Pengunjung yang salah ketik pantas dikembalikan ke situs,
 * bukan ditinggalkan di luar.
 */
const PESAN: Record<number, { judul: string; deskripsi: string }> = {
  403: {
    judul: 'Tidak Punya Akses',
    deskripsi: 'Anda tidak memiliki izin untuk membuka halaman ini.',
  },
  404: {
    judul: 'Halaman Tidak Ditemukan',
    deskripsi: 'Alamat yang Anda tuju tidak tersedia pada situs ini.',
  },
  419: {
    judul: 'Sesi Berakhir',
    deskripsi: 'Halaman terlalu lama dibiarkan terbuka. Silakan muat ulang lalu coba lagi.',
  },
  500: {
    judul: 'Terjadi Kesalahan',
    deskripsi: 'Ada gangguan pada server kami. Silakan coba beberapa saat lagi.',
  },
  503: {
    judul: 'Situs Sedang Dirawat',
    deskripsi: 'Situs sedang dalam pemeliharaan. Silakan kembali beberapa saat lagi.',
  },
}

export default function Galat({ status }: { status: number }) {
  const { judul, deskripsi } = PESAN[status] ?? PESAN[500]

  return (
    <>
      <Head title={judul} />

      <IsiHalaman lebar="sempit">
        <Kartu className="flex flex-col items-center gap-4 px-6 py-16 text-center">
          <p className="font-heading text-5xl font-bold text-gold tabular-nums">{status}</p>

          <div>
            <h1 className="font-heading text-xl font-bold text-navy">{judul}</h1>
            <p className="mx-auto mt-2 max-w-sm text-sm text-slate-600">{deskripsi}</p>
          </div>

          <Link
            href="/"
            className="font-heading mt-2 inline-flex items-center gap-2 rounded-full bg-navy px-6 py-2.5 text-sm font-bold text-white transition hover:bg-navy-light"
          >
            Kembali ke Beranda
          </Link>
        </Kartu>
      </IsiHalaman>
    </>
  )
}

Galat.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
