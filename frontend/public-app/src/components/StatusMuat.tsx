import type { ReactNode } from 'react'
import { AlertTriangle } from 'lucide-react'
import { EmptyState } from './EmptyState'
import { KepalaHalaman, IsiHalaman, Kartu } from './ui'

/**
 * Pembungkus state pemuatan data untuk halaman publik.
 *
 * Menyatukan tiga keadaan (memuat / gagal / kosong) di satu tempat agar setiap
 * modul menampilkannya secara seragam, dan agar keadaan "kosong" tetap memakai
 * pola empty-state informatif yang diminta PRD 3.2 — bukan halaman putih.
 */
export function StatusMuat({
  memuat,
  galat,
  kosong,
  judul,
  deskripsi,
  eyebrow,
  children,
}: {
  memuat: boolean
  galat: unknown
  kosong?: boolean
  judul: string
  deskripsi: string
  eyebrow?: string
  children: ReactNode
}) {
  if (memuat) {
    return (
      <>
        <KepalaHalaman eyebrow={eyebrow} judul={judul} deskripsi={deskripsi} />

        <IsiHalaman>
          {/*
            Kerangka yang menyerupai bentuk akhir konten, bukan pemintal.
            Pengunjung dapat menduga apa yang sedang datang, dan pergeseran
            tata letak saat data tiba jauh berkurang.
          */}
          <div aria-hidden="true" className="space-y-4">
            <div className="h-5 w-1/3 animate-pulse rounded bg-navy/10" />
            <Kartu className="space-y-3 p-6">
              <div className="h-4 w-full animate-pulse rounded bg-navy/5" />
              <div className="h-4 w-5/6 animate-pulse rounded bg-navy/5" />
              <div className="h-4 w-2/3 animate-pulse rounded bg-navy/5" />
            </Kartu>
            <Kartu className="space-y-3 p-6">
              <div className="h-4 w-3/4 animate-pulse rounded bg-navy/5" />
              <div className="h-4 w-1/2 animate-pulse rounded bg-navy/5" />
            </Kartu>
          </div>
          <p className="sr-only" role="status">
            Memuat data…
          </p>
        </IsiHalaman>
      </>
    )
  }

  if (galat) {
    return (
      <>
        <KepalaHalaman eyebrow={eyebrow} judul={judul} deskripsi={deskripsi} />

        <IsiHalaman>
          <Kartu className="flex flex-col items-center gap-4 px-6 py-16 text-center">
            <span
              aria-hidden="true"
              className="grid size-14 place-items-center rounded-full bg-amber-50 text-amber-600"
            >
              <AlertTriangle className="size-7" />
            </span>

            <div>
              <p className="font-heading text-lg font-bold text-navy">Gagal Memuat Data</p>
              <p className="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                Periksa koneksi internet Anda, lalu muat ulang halaman ini.
              </p>
            </div>

            <button
              onClick={() => window.location.reload()}
              className="rounded-lg border-2 border-navy/20 bg-white px-5 py-2 text-sm font-semibold text-navy transition hover:border-navy/40"
            >
              Muat Ulang
            </button>
          </Kartu>
        </IsiHalaman>
      </>
    )
  }

  if (kosong) {
    return <EmptyState eyebrow={eyebrow} judul={judul} deskripsi={deskripsi} />
  }

  return <>{children}</>
}
