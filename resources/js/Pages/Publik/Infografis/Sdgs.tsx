import { Head } from '@inertiajs/react'
import { EmptyState } from '@/Components/EmptyState'
import { IsiHalaman } from '@/Components/ui'
import { KartuAngka } from '@/Components/viz/Grafik'
import { bungkusInfografis } from '@/Layouts/LayoutInfografis'
import type { InfografisSdgs } from '@/types/api'

const DESKRIPSI =
  'Capaian 18 tujuan pembangunan berkelanjutan tingkat desa versi Kemendes PDTT.'

/**
 * Infografis SDGs Desa — PRD 6.8.
 *
 * Ditampilkan sebagai grid 18 kartu. Skor dikodekan lewat panjang bilah DAN
 * angka tertulis, bukan warna semata — dengan 18 tujuan, memberi tiap tujuan
 * warnanya sendiri justru membuat halaman mustahil dibaca.
 */
export default function Sdgs({ data }: { data: InfografisSdgs | null }) {
  if (!data) {
    return (
      <>
        <Head title="SDGs Desa" />
        <EmptyState judul="SDGs Desa" deskripsi={DESKRIPSI} />
      </>
    )
  }

  return (
    <IsiHalaman lebar="lebar">
      <Head title={`SDGs Desa ${data.tahun}`} />

      <div className="max-w-xs">
        <KartuAngka
          label="Skor Rata-rata"
          nilai={data.skor_rata_rata.toFixed(2).replace('.', ',')}
          keterangan={`Dari ${data.goals.length} tujuan yang dinilai`}
        />
      </div>

      <ul className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {data.goals.map((goal) => (
          <li
            key={goal.goal_number}
            className="rounded-xl border border-navy/10 bg-white p-4 shadow-sm"
          >
            <div className="flex items-baseline gap-3">
              <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-navy text-sm font-semibold text-white tabular-nums">
                {goal.goal_number}
              </span>
              <h2 className="text-sm font-semibold text-navy">{goal.nama_goal}</h2>
            </div>

            <div className="mt-4 flex items-center gap-3">
              {/* Bilah kemajuan; nilainya tetap ditulis di sebelahnya agar
                  tidak bergantung pada penglihatan warna/panjang saja. */}
              <div
                className="h-2 flex-1 overflow-hidden rounded-full bg-navy/5"
                role="img"
                aria-label={`Skor ${goal.skor ?? 0} dari 100`}
              >
                <div
                  className="h-full rounded-full bg-[var(--viz-series-1)]"
                  style={{ width: `${Math.min(goal.skor ?? 0, 100)}%` }}
                />
              </div>
              <span className="text-sm font-bold text-navy tabular-nums">
                {goal.skor?.toFixed(0) ?? '—'}
              </span>
            </div>

            {goal.deskripsi_capaian && (
              <p className="mt-3 text-xs text-slate-600">{goal.deskripsi_capaian}</p>
            )}
          </li>
        ))}
      </ul>
    </IsiHalaman>
  )
}

Sdgs.layout = bungkusInfografis
