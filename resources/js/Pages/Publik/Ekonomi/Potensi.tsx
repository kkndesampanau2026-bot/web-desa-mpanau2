import type { ReactNode } from 'react'
import { Head, router } from '@inertiajs/react'
import { EmptyState } from '@/Components/EmptyState'
import { ChipFilter } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import type { DaftarPotensi } from '@/types/api'

const DESKRIPSI =
  'Potensi ekonomi, pariwisata, pertanian, dan industri kreatif yang dimiliki desa.'

/** Potensi Desa — PRD 6.11. */
export default function Potensi({
  data,
  filter,
}: {
  data: DaftarPotensi | null
  filter: { kategori: string | null }
}) {
  function gantiKategori(pilihan?: string) {
    router.get(
      '/potensi',
      pilihan ? { kategori: pilihan } : {},
      { preserveState: true, preserveScroll: true, replace: true },
    )
  }

  // Hanya dianggap kosong bila tanpa filter — kategori yang kebetulan kosong
  // ditangani sebagai pesan tersendiri di bawah.
  if (!data && !filter.kategori) {
    return (
      <>
        <Head title="Potensi Desa" />
        <EmptyState judul="Potensi Desa" deskripsi={DESKRIPSI} />
      </>
    )
  }

  return (
    <div className="mx-auto max-w-5xl px-6 py-10">
      <Head title="Potensi Desa" />

      <h1 className="font-heading text-2xl font-bold text-navy">Potensi Desa</h1>
      <p className="mt-2 text-slate-600">{DESKRIPSI}</p>

      {data && data.kategori_tersedia.length > 0 && (
        <nav aria-label="Filter kategori" className="mt-6 flex flex-wrap gap-2">
          <ChipFilter aktif={!filter.kategori} onClick={() => gantiKategori()}>
            Semua
          </ChipFilter>
          {data.kategori_tersedia.map((k) => (
            <ChipFilter
              key={k}
              aktif={filter.kategori === k}
              onClick={() => gantiKategori(k)}
            >
              {k}
            </ChipFilter>
          ))}
        </nav>
      )}

      {!data?.items.length ? (
        <p className="mt-10 rounded-lg border border-dashed border-navy/20 bg-navy/3 px-6 py-10 text-center text-slate-600">
          Belum ada potensi desa pada kategori ini.
        </p>
      ) : (
        <ul className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {data.items.map((p) => (
            <li
              key={p.id}
              className="overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm transition hover:shadow-sm"
            >
              {p.foto ? (
                <img src={p.foto} alt="" loading="lazy" className="h-40 w-full object-cover" />
              ) : (
                <div aria-hidden="true" className="h-40 w-full bg-navy/5" />
              )}

              <div className="p-4">
                <span className="text-xs font-medium text-slate-500">{p.kategori}</span>
                <h2 className="mt-1 font-semibold text-navy">{p.judul}</h2>
                {p.deskripsi && <p className="mt-1 text-sm text-slate-600">{p.deskripsi}</p>}
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

Potensi.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
