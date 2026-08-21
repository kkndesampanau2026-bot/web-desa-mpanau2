import { BarChart3 } from 'lucide-react'
import { useStatistikKunjungan } from '@/lib/queries'
import { formatAngka } from '@/lib/format'
import type { StatistikKunjungan } from '@/types/api'

/**
 * Widget statistik kunjungan — PRD 6.16 & 3.2.
 *
 * Tujuh kategori, tampil konsisten di footer SETIAP halaman (bukan hanya
 * beranda), mengikuti pola situs referensi.
 */
const KATEGORI: [keyof StatistikKunjungan, string][] = [
  ['hari_ini', 'Hari Ini'],
  ['kemarin', 'Kemarin'],
  ['minggu_ini', 'Minggu Ini'],
  ['minggu_lalu', 'Minggu Lalu'],
  ['bulan_ini', 'Bulan Ini'],
  ['bulan_lalu', 'Bulan Lalu'],
  ['total', 'Total'],
]

export function WidgetStatistik() {
  const { data, isPending } = useStatistikKunjungan()

  // Widget ini bersifat pelengkap: bila gagal dimuat, ia menghilang diam-diam
  // alih-alih menampilkan galat yang mengganggu di footer setiap halaman.
  if (isPending || !data) return null

  return (
    <section aria-label="Statistik kunjungan">
      <h2 className="flex items-center gap-2 text-sm font-bold tracking-wide text-white uppercase">
        <BarChart3 className="size-4 text-gold" aria-hidden="true" />
        Statistik Kunjungan
      </h2>

      <dl className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
        {KATEGORI.map(([kunci, label]) => (
          <div
            key={kunci}
            className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-center"
          >
            <dt className="text-[11px] text-white/60">{label}</dt>
            <dd className="font-heading mt-0.5 font-bold text-gold tabular-nums">
              {formatAngka(data[kunci])}
            </dd>
          </div>
        ))}
      </dl>
    </section>
  )
}
