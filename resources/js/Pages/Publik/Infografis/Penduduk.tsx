import { Head } from '@inertiajs/react'
import { EmptyState } from '@/Components/EmptyState'
import { IsiHalaman } from '@/Components/ui'
import { BatangKategori, GarisTren, KartuAngka, Lingkaran } from '@/Components/viz/Grafik'
import { bungkusInfografis } from '@/Layouts/LayoutInfografis'
import type { InfografisPenduduk } from '@/types/api'

const DESKRIPSI =
  'Statistik penduduk desa: jumlah jiwa dan kepala keluarga, sebaran per dusun, kelompok umur, pendidikan, pekerjaan, status perkawinan, agama, dan wajib pilih.'

/** Infografis Kependudukan — PRD 6.3. */
export default function Penduduk({ data }: { data: InfografisPenduduk | null }) {
  if (!data) {
    return (
      <>
        <Head title="Infografis Kependudukan" />
        <EmptyState judul="Infografis Kependudukan" deskripsi={DESKRIPSI} />
      </>
    )
  }

  return (
    <IsiHalaman lebar="lebar">
      <Head title="Infografis Kependudukan" />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <Lingkaran
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

        <Lingkaran judul="Kelompok Umur" data={data.breakdown.kelompok_umur} />
        <Lingkaran judul="Status Perkawinan" data={data.breakdown.perkawinan} />
        <Lingkaran judul="Agama" data={data.breakdown.agama} />

        {/* Label panjang dan kategori banyak: batang tetap lebih terbaca
            daripada juring lingkaran. */}
        <BatangKategori judul="Sebaran per Dusun" data={data.breakdown.dusun} />
        <BatangKategori judul="Tingkat Pendidikan" data={data.breakdown.pendidikan} />
        <BatangKategori judul="Jenis Pekerjaan" data={data.breakdown.pekerjaan} />
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
    </IsiHalaman>
  )
}

Penduduk.layout = bungkusInfografis
