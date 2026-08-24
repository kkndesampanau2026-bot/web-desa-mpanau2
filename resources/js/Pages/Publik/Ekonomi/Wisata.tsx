import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { EmptyState } from '@/Components/EmptyState'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import type { WisataRingkas } from '@/types/api'

const DESKRIPSI = 'Destinasi wisata desa beserta lokasi, jam operasional, dan fasilitasnya.'

/** Daftar destinasi wisata — PRD 6.11. */
export default function Wisata({ wisata }: { wisata: WisataRingkas[] | null }) {
  if (!wisata?.length) {
    return (
      <>
        <Head title="Wisata Desa" />
        <EmptyState judul="Wisata Desa" deskripsi={DESKRIPSI} />
      </>
    )
  }

  return (
    <div className="mx-auto max-w-5xl px-6 py-10">
      <Head title="Wisata Desa" />

      <h1 className="font-heading text-2xl font-bold text-navy">Wisata Desa</h1>
      <p className="mt-2 text-slate-600">{DESKRIPSI}</p>

      <ul className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {wisata.map((w) => (
          <li
            key={w.id}
            className="overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm transition hover:shadow-sm"
          >
            <Link href={`/wisata/${w.slug}`} className="block">
              {w.foto_utama ? (
                <img
                  src={w.foto_utama}
                  alt=""
                  loading="lazy"
                  className="h-44 w-full object-cover"
                />
              ) : (
                <div aria-hidden="true" className="h-44 w-full bg-navy/5" />
              )}

              <div className="p-4">
                <h2 className="font-semibold text-navy">{w.nama}</h2>
                {w.deskripsi && (
                  <p className="mt-1 line-clamp-2 text-sm text-slate-600">{w.deskripsi}</p>
                )}
                {w.harga_tiket && (
                  <p className="mt-2 text-sm font-medium text-slate-700">
                    Tiket: {w.harga_tiket}
                  </p>
                )}
              </div>
            </Link>
          </li>
        ))}
      </ul>
    </div>
  )
}

Wisata.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
