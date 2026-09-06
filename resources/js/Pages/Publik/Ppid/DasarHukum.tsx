import type { ReactNode } from 'react'
import { Head } from '@inertiajs/react'
import { Gavel } from 'lucide-react'
import { EmptyState } from '@/Components/EmptyState'
import { AksiBerkasPdf, IsiHalaman, Kartu, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import type { DasarHukumPpid } from '@/types/api'

const DESKRIPSI = 'Regulasi yang menjadi dasar penerapan keterbukaan informasi publik di desa.'

export default function DasarHukum({ dasar_hukum: daftar }: { dasar_hukum: DasarHukumPpid[] }) {
  if (daftar.length === 0) {
    return (
      <>
        <Head title="Dasar Hukum PPID" />
        <EmptyState eyebrow="PPID" judul="Dasar Hukum" deskripsi={DESKRIPSI} />
      </>
    )
  }

  return (
    <>
      <Head title="Dasar Hukum PPID" />

      <KepalaHalaman eyebrow="PPID" judul="Dasar Hukum" deskripsi={DESKRIPSI} lebar="sedang" />


      <IsiHalaman>
        <ol className="space-y-4">
          {daftar.map((d, i) => (
            <li key={d.judul_regulasi}>
              <Kartu interaktif className="flex items-start gap-5 p-6">
                <span
                  aria-hidden="true"
                  className="font-heading grid size-10 shrink-0 place-items-center rounded-xl bg-gold/15 font-bold text-gold-dark tabular-nums"
                >
                  {i + 1}
                </span>

                <div className="min-w-0 flex-1">
                  <h2 className="font-heading text-base font-bold text-navy sm:text-lg">
                    {d.judul_regulasi}
                  </h2>

                  {(d.nomor_regulasi || d.tahun) && (
                    <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                      <Gavel className="size-4 text-slate-400" aria-hidden="true" />
                      {[d.nomor_regulasi, d.tahun].filter(Boolean).join(' · ')}
                    </p>
                  )}

                  {d.file && (
                    <AksiBerkasPdf
                      file={d.file}
                      nama={d.judul_regulasi}
                      className="mt-3"
                    />
                  )}
                </div>
              </Kartu>
            </li>
          ))}
        </ol>
      </IsiHalaman>
    </>
  )
}

DasarHukum.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
