import type { ReactNode } from 'react'
import { Head } from '@inertiajs/react'
import { CalendarRange, User } from 'lucide-react'
import { EmptyState } from '@/Components/EmptyState'
import { IsiHalaman, Kartu, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatTanggal } from '@/lib/format'
import type { Official } from '@/types/api'

const DESKRIPSI = 'Susunan aparat pemerintah desa beserta jabatan dan masa baktinya.'

export default function Pemerintah({ aparat }: { aparat: Official[] }) {
  if (aparat.length === 0) {
    return (
      <>
        <Head title="Pemerintah Desa" />
        <EmptyState
          eyebrow="Struktur Organisasi"
          judul="Pemerintah Desa"
          deskripsi={DESKRIPSI}
        />
      </>
    )
  }

  return (
    <>
      <Head title="Pemerintah Desa" />

      <KepalaHalaman
        eyebrow="Struktur Organisasi"
        judul="Pemerintah Desa"
        deskripsi={DESKRIPSI}
      />

      <IsiHalaman lebar="lebar">
        <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {aparat.map((o) => (
            <li key={o.id}>
              <Kartu interaktif className="h-full p-6 text-center">
                {o.foto ? (
                  <img
                    src={o.foto}
                    alt=""
                    loading="lazy"
                    className="mx-auto size-24 rounded-full border-4 border-gold object-cover"
                  />
                ) : (
                  <span
                    aria-hidden="true"
                    className="mx-auto grid size-24 place-items-center rounded-full border-4 border-gold/40 bg-navy/5 text-navy/30"
                  >
                    <User className="size-9" />
                  </span>
                )}

                <h2 className="font-heading mt-4 font-bold text-navy">{o.nama}</h2>

                <p className="mt-1 inline-block rounded-full bg-navy/5 px-3 py-1 text-xs font-semibold text-navy">
                  {o.jabatan}
                </p>

                {o.periode_mulai && (
                  <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-slate-500">
                    <CalendarRange className="size-3.5" aria-hidden="true" />
                    {formatTanggal(o.periode_mulai)}
                    {o.periode_selesai && <> – {formatTanggal(o.periode_selesai)}</>}
                  </p>
                )}
              </Kartu>
            </li>
          ))}
        </ul>
      </IsiHalaman>
    </>
  )
}

Pemerintah.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
