import { StatusMuat } from '@/components/StatusMuat'
import { GarisTren, KartuAngka } from '@/components/viz/Grafik'
import { useInfografisApbdes } from '@/lib/queries'
import { formatRupiah, formatRupiahRingkas } from '@/lib/format'

const DESKRIPSI =
  'Anggaran Pendapatan dan Belanja Desa: rincian pendapatan, belanja, dan pembiayaan beserta tren antar tahun anggaran.'

/** Infografis APBDes — PRD 6.4. */
export function ApbdesPage() {
  const { data, isPending, error } = useInfografisApbdes()

  const surplus = data?.ringkasan.surplus_defisit ?? 0

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      kosong={!data}
      judul="APBDes"
      deskripsi={DESKRIPSI}
    >
      {data && (
        <div className="mx-auto max-w-5xl px-6 py-10">
          <h1 className="font-heading text-2xl font-bold text-navy">APBDes {data.tahun}</h1>
          <p className="mt-2 text-slate-600">{DESKRIPSI}</p>

          <div className="mt-8 grid gap-4 sm:grid-cols-3">
            <KartuAngka
              label="Total Pendapatan"
              nilai={formatRupiah(data.ringkasan.total_pendapatan)}
            />
            <KartuAngka label="Total Belanja" nilai={formatRupiah(data.ringkasan.total_belanja)} />
            <KartuAngka
              label={surplus >= 0 ? 'Surplus' : 'Defisit'}
              nilai={formatRupiah(Math.abs(surplus))}
              keterangan="Total Pendapatan − Total Belanja"
            />
          </div>

          {data.tren.length > 0 && (
            <div className="mt-12">
              <GarisTren
                judul="Pendapatan dan Belanja dari Tahun ke Tahun"
                data={data.tren as unknown as Record<string, string | number>[]}
                sumbuX="tahun"
                formatNilai={formatRupiahRingkas}
                deret={[
                  { kunci: 'pendapatan', label: 'Pendapatan', warna: 'var(--viz-series-1)' },
                  { kunci: 'belanja', label: 'Belanja', warna: 'var(--viz-series-2)' },
                ]}
              />
            </div>
          )}

          {/* Rincian disajikan sebagai tabel, bukan grafik: pembaca APBDes
              mencari angka tepat per mata anggaran, bukan perbandingan visual. */}
          <div className="mt-12 space-y-8">
            {data.kelompok.map((kelompok) => (
              <section key={kelompok.kelompok}>
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                  <h2 className="font-heading text-lg font-bold text-navy">{kelompok.kelompok}</h2>
                  <p className="font-medium text-slate-700 tabular-nums">
                    {formatRupiah(kelompok.total_anggaran)}
                  </p>
                </div>

                <div className="mt-3 overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-navy/10 text-left text-slate-500">
                        <th scope="col" className="py-2 font-medium">Uraian</th>
                        <th scope="col" className="py-2 text-right font-medium">Anggaran</th>
                        <th scope="col" className="py-2 text-right font-medium">Realisasi</th>
                      </tr>
                    </thead>
                    <tbody>
                      {kelompok.kategori.map((kategori) => (
                        <tr key={kategori.nama} className="border-b border-navy/5">
                          <td className="py-2 text-slate-800">{kategori.nama}</td>
                          <td className="py-2 text-right text-slate-800 tabular-nums">
                            {formatRupiah(kategori.total_anggaran)}
                          </td>
                          <td className="py-2 text-right text-slate-600 tabular-nums">
                            {kategori.total_realisasi > 0
                              ? formatRupiah(kategori.total_realisasi)
                              : '—'}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </section>
            ))}
          </div>
        </div>
      )}
    </StatusMuat>
  )
}
