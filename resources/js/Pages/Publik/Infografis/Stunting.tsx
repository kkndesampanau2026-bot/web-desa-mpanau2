import { Head } from '@inertiajs/react'
import { EmptyState } from '@/Components/EmptyState'
import { IsiHalaman } from '@/Components/ui'
import { GarisTren, KartuAngka } from '@/Components/viz/Grafik'
import { bungkusInfografis } from '@/Layouts/LayoutInfografis'
import { formatPersen } from '@/lib/format'
import type { InfografisStunting } from '@/types/api'

const DESKRIPSI =
  'Prevalensi stunting pada balita desa per periode, disajikan dalam bentuk agregat.'

/**
 * Infografis Stunting — PRD 6.5.
 *
 * Seluruh angka bersifat agregat. Tidak ada identitas balita yang ditampilkan,
 * dan sumber datanya memang tidak menyimpannya.
 */
export default function Stunting({ data }: { data: InfografisStunting | null }) {
  if (!data) {
    return (
      <>
        <Head title="Data Stunting" />
        <EmptyState judul="Data Stunting" deskripsi={DESKRIPSI} />
      </>
    )
  }

  // Prevalensi dihitung ulang dari data mentah agar riwayat punya nilai
  // persentase untuk digambar sebagai tren.
  const riwayat = data.riwayat.map((r) => ({
    periode: r.periode,
    prevalensi:
      r.jumlah_balita_diukur > 0
        ? Number(((r.jumlah_kasus_stunting / r.jumlah_balita_diukur) * 100).toFixed(2))
        : 0,
  }))

  return (
    <IsiHalaman lebar="lebar">
      <Head title="Data Stunting" />

      <div className="grid gap-4 sm:grid-cols-3">
        <KartuAngka
          label="Balita Diukur"
          nilai={data.ringkasan.jumlah_balita_diukur}
          satuan="balita"
        />
        <KartuAngka
          label="Kasus Stunting"
          nilai={data.ringkasan.jumlah_kasus_stunting}
          satuan="balita"
        />
        <KartuAngka
          label="Prevalensi"
          nilai={formatPersen(data.ringkasan.persentase_prevalensi)}
          keterangan="Kasus stunting dibagi balita yang diukur"
        />
      </div>

      {riwayat.length > 1 && (
        <div className="mt-12">
          <GarisTren
            judul="Tren Prevalensi Stunting"
            data={riwayat as unknown as Record<string, string | number>[]}
            sumbuX="periode"
            formatNilai={(n) => `${n.toString().replace('.', ',')}%`}
            deret={[
              { kunci: 'prevalensi', label: 'Prevalensi (%)', warna: 'var(--viz-series-1)' },
            ]}
          />
        </div>
      )}

      {data.per_dusun.length > 0 && (
        <section className="mt-12">
          <h2 className="font-heading text-lg font-bold text-navy">Rincian per Dusun</h2>

          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-navy/10 text-left text-slate-500">
                  <th scope="col" className="py-2 font-medium">
                    Dusun
                  </th>
                  <th scope="col" className="py-2 text-right font-medium">
                    Balita Diukur
                  </th>
                  <th scope="col" className="py-2 text-right font-medium">
                    Kasus
                  </th>
                  <th scope="col" className="py-2 text-right font-medium">
                    Prevalensi
                  </th>
                </tr>
              </thead>
              <tbody>
                {data.per_dusun.map((d) => (
                  <tr key={d.dusun} className="border-b border-navy/5">
                    <td className="py-2 text-slate-800">{d.dusun}</td>
                    <td className="py-2 text-right text-slate-800 tabular-nums">
                      {d.jumlah_balita_diukur}
                    </td>
                    <td className="py-2 text-right text-slate-800 tabular-nums">
                      {d.jumlah_kasus_stunting}
                    </td>
                    <td className="py-2 text-right text-slate-800 tabular-nums">
                      {formatPersen(d.persentase_prevalensi)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </IsiHalaman>
  )
}

Stunting.layout = bungkusInfografis
