import { StatusMuat } from '@/components/StatusMuat'
import { BatangKategori, BatangPerbandingan, GarisTren, KartuAngka } from '@/components/viz/Grafik'
import { useInfografisPenduduk } from '@/lib/queries'

const DESKRIPSI =
  'Statistik penduduk desa: jumlah jiwa dan kepala keluarga, sebaran per dusun, kelompok umur, pendidikan, pekerjaan, status perkawinan, agama, dan wajib pilih.'

/** Infografis Kependudukan — PRD 6.3. */
export function PendudukPage() {
  const { data, isPending, error } = useInfografisPenduduk()

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      kosong={!data}
      judul="Infografis Kependudukan"
      deskripsi={DESKRIPSI}
    >
      {data && (
        <div className="mx-auto max-w-5xl px-6 py-10">
          <h1 className="font-heading text-2xl font-bold text-navy">Infografis Kependudukan</h1>
          <p className="mt-2 text-slate-600">{DESKRIPSI}</p>
          <p className="mt-1 text-sm text-slate-500">Data periode {data.periode}.</p>

          <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <KartuAngka label="Total Penduduk" nilai={data.ringkasan.total_penduduk} satuan="jiwa" />
            <KartuAngka label="Kepala Keluarga" nilai={data.ringkasan.total_kk} satuan="KK" />
            <KartuAngka
              label="Wajib Pilih"
              nilai={data.ringkasan.total_wajib_pilih}
              satuan="jiwa"
              keterangan="Usia ≥17 tahun atau sudah kawin"
            />
            <KartuAngka
              label="Rata-rata per KK"
              nilai={
                data.ringkasan.total_kk > 0
                  ? (data.ringkasan.total_penduduk / data.ringkasan.total_kk).toFixed(1)
                  : '—'
              }
              satuan="jiwa"
            />
          </div>

          <div className="mt-10 grid gap-10 lg:grid-cols-2">
            <BatangPerbandingan
              judul="Jenis Kelamin"
              data={[
                {
                  label: 'Laki-laki',
                  jumlah: data.ringkasan.total_laki,
                  warna: 'var(--viz-series-1)',
                },
                {
                  label: 'Perempuan',
                  jumlah: data.ringkasan.total_perempuan,
                  warna: 'var(--viz-series-2)',
                },
              ]}
            />

            <BatangKategori judul="Kelompok Umur" data={data.breakdown.kelompok_umur} />
            <BatangKategori judul="Sebaran per Dusun" data={data.breakdown.dusun} />
            <BatangKategori judul="Tingkat Pendidikan" data={data.breakdown.pendidikan} />
            <BatangKategori judul="Jenis Pekerjaan" data={data.breakdown.pekerjaan} />
            <BatangKategori judul="Status Perkawinan" data={data.breakdown.perkawinan} />
            <BatangKategori judul="Agama" data={data.breakdown.agama} />
          </div>

          {data.riwayat.length > 1 && (
            <div className="mt-12">
              <GarisTren
                judul="Perkembangan Jumlah Penduduk"
                data={data.riwayat as unknown as Record<string, string | number>[]}
                sumbuX="periode"
                deret={[
                  {
                    kunci: 'total_penduduk',
                    label: 'Jumlah Penduduk',
                    warna: 'var(--viz-series-1)',
                  },
                ]}
              />
            </div>
          )}
        </div>
      )}
    </StatusMuat>
  )
}
