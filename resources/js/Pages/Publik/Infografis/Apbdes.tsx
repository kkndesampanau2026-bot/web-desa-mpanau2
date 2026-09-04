import { useId } from 'react'
import { Head, router } from '@inertiajs/react'
import { EmptyState } from '@/Components/EmptyState'
import { IsiHalaman, Pilihan } from '@/Components/ui'
import { GarisTren, KartuAngka, Lingkaran } from '@/Components/viz/Grafik'
import { bungkusInfografis } from '@/Layouts/LayoutInfografis'
import { formatRupiah, formatRupiahRingkas } from '@/lib/format'
import type { InfografisApbdes } from '@/types/api'

const DESKRIPSI =
  'Anggaran Pendapatan dan Belanja Desa: rincian pendapatan, belanja, dan pembiayaan beserta tren antar tahun anggaran.'

/** Infografis APBDes — PRD 6.4. */
export default function Apbdes({ data }: { data: InfografisApbdes | null }) {
  if (!data) {
    return (
      <>
        <Head title="APBDes" />
        <EmptyState judul="APBDes" deskripsi={DESKRIPSI} />
      </>
    )
  }

  const surplus = data.ringkasan.surplus_defisit

  // Komposisi per kelompok: proporsi tiap kategori terhadap total kelompoknya.
  const juringKelompok = (nama: string) =>
    (data.kelompok.find((k) => k.kelompok === nama)?.kategori ?? []).map((kategori) => ({
      label: kategori.nama,
      jumlah: kategori.total_anggaran,
    }))

  const komposisiPendapatan = juringKelompok('Pendapatan')
  const komposisiBelanja = juringKelompok('Belanja')

  return (
    <IsiHalaman lebar="lebar">
      <Head title={`APBDes ${data.tahun}`} />

      <PemilihTahun tahun={data.tahun} tersedia={data.tahun_tersedia} />

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

      {(komposisiPendapatan.length > 0 || komposisiBelanja.length > 0) && (
        <div className="mt-6 grid gap-6 lg:grid-cols-2">
          {komposisiPendapatan.length > 0 && (
            <Lingkaran
              judul="Komposisi Pendapatan"
              data={komposisiPendapatan}
              formatNilai={formatRupiahRingkas}
            />
          )}
          {komposisiBelanja.length > 0 && (
            <Lingkaran
              judul="Komposisi Belanja"
              data={komposisiBelanja}
              formatNilai={formatRupiahRingkas}
            />
          )}
        </div>
      )}

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
                    <th scope="col" className="py-2 font-medium">
                      Uraian
                    </th>
                    <th scope="col" className="py-2 text-right font-medium">
                      Anggaran
                    </th>
                    <th scope="col" className="py-2 text-right font-medium">
                      Realisasi
                    </th>
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
    </IsiHalaman>
  )
}

/**
 * Pemilih tahun anggaran.
 *
 * Tahun dipilih lewat query string, bukan state React: alamat halaman menjadi
 * dapat dibagikan dan ditandai (mis. /infografis/apbdes?tahun=2024), dan data
 * tetap dirakit di server bersama seluruh isi halaman. `replace` dipakai agar
 * berpindah-pindah tahun tidak menumpuk riwayat peramban.
 *
 * Hanya satu tahun terbit? Dropdown tak ada gunanya — cukup tampilkan tahunnya.
 */
function PemilihTahun({ tahun, tersedia }: { tahun: number; tersedia: number[] }) {
  const id = useId()

  if (tersedia.length < 2) {
    return <p className="text-center text-sm text-slate-500">Tahun Anggaran {tahun}</p>
  }

  return (
    <div className="flex flex-wrap items-center justify-center gap-2">
      <label htmlFor={id} className="text-sm text-slate-500">
        Tahun Anggaran
      </label>

      <Pilihan
        id={id}
        value={String(tahun)}
        onChange={(v) =>
          router.get(
            '/infografis/apbdes',
            { tahun: v },
            { preserveScroll: true, replace: true },
          )
        }
        className="flex items-center gap-2 rounded-full border-2 border-navy/20 bg-white px-4 py-1.5 text-sm font-semibold text-navy transition outline-none hover:border-navy/40 focus:border-navy"
        options={tersedia.map((t) => ({ value: String(t), label: String(t) }))}
      />
    </div>
  )
}

Apbdes.layout = bungkusInfografis
