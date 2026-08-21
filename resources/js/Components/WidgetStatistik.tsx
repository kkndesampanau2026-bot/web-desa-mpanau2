import { BarChart3 } from 'lucide-react'
import { formatAngka } from '@/lib/format'
import { useHalaman } from '@/types/inertia'
import type { StatistikKunjungan } from '@/types/api'

/**
 * Widget statistik kunjungan — PRD 6.16 & 3.2.
 *
 * Tujuh kategori, tampil konsisten di footer SETIAP halaman (bukan hanya
 * beranda), mengikuti pola situs referensi.
 *
 * Angkanya kini datang sebagai prop bersama Inertia, bukan permintaan HTTP
 * tersendiri. Karena ia berada di footer setiap halaman, pola lama berarti
 * satu permintaan tambahan pada setiap navigasi.
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
  const { statistik_kunjungan: data } = useHalaman().props

  // Widget ini bersifat pelengkap: bila datanya tidak ada, ia menghilang
  // diam-diam alih-alih menampilkan galat di footer setiap halaman.
  if (!data) return null

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
