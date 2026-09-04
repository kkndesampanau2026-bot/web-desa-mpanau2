import { Head } from '@inertiajs/react'
import { EmptyState } from '@/Components/EmptyState'
import { IsiHalaman } from '@/Components/ui'
import { BatangKategori, KartuAngka } from '@/Components/viz/Grafik'
import { bungkusInfografis } from '@/Layouts/LayoutInfografis'
import type { InfografisBansos } from '@/types/api'

const DESKRIPSI =
  'Rekapitulasi jumlah penerima bantuan sosial desa per jenis bantuan pada tahun anggaran berjalan.'

/**
 * Infografis Bansos — PRD 6.6 & 10.3.
 *
 * Halaman ini hanya menyajikan ANGKA AGREGAT: total penerima dan jumlah
 * penerima per jenis bantuan. Tidak ada penelusuran per orang di sini dan
 * tidak ada daftar penerima — identitas warga penerima bantuan tidak
 * dipublikasikan, dan cara paling andal menjamin itu adalah dengan tidak
 * menyediakan jalurnya sama sekali.
 */
export default function Bansos({ data }: { data: InfografisBansos | null }) {
  if (!data) {
    return (
      <>
        <Head title="Bantuan Sosial" />
        <EmptyState judul="Bantuan Sosial" deskripsi={DESKRIPSI} />
      </>
    )
  }

  return (
    <IsiHalaman lebar="lebar">
      <Head title={`Bantuan Sosial ${data.tahun_anggaran}`} />

      {/*
        Kartu angka disandingkan dengan keterangannya, bukan berdiri sendiri
        di kolom sempit: satu angka tanpa konteks menyisakan ruang kosong lebar
        di sebelahnya, dan pembaca tidak tahu apa yang ditampilkan — maupun ke
        mana harus bertanya soal status bantuannya sendiri.
      */}
      <section className="grid gap-6 sm:grid-cols-[minmax(0,17rem)_1fr] sm:items-center">
        <KartuAngka
          label="Total Penerima"
          nilai={data.total_penerima}
          satuan="jiwa"
          keterangan={`Tahun anggaran ${data.tahun_anggaran}`}
        />

        <p className="text-sm leading-relaxed text-slate-600">
          Angka di samping adalah jumlah penerima bantuan sosial yang tercatat pada tahun
          anggaran {data.tahun_anggaran}. Data disajikan sebagai rekapitulasi;{' '}
          <strong className="font-semibold text-navy">
            identitas penerima tidak dipublikasikan
          </strong>{' '}
          demi menjaga kerahasiaan data warga. Untuk memastikan status bantuan atas nama Anda,
          silakan menghubungi kantor desa dengan membawa identitas diri.
        </p>
      </section>

      <div className="mt-10">
        <BatangKategori
          judul="Jumlah Penerima per Jenis Bantuan"
          data={data.per_jenis.map((j) => ({
            label: j.jenis_bantuan,
            jumlah: j.jumlah_penerima,
          }))}
        />
      </div>

      {data.per_jenis.some((j) => j.deskripsi || j.sumber_dana) && (
        <section className="mt-10">
          <h2 className="font-heading text-lg font-bold text-navy">Jenis Bantuan</h2>
          <ul className="mt-3 space-y-3">
            {data.per_jenis.map((j) => (
              <li
                key={j.jenis_bantuan}
                className="rounded-xl border border-navy/10 bg-white p-4 shadow-sm"
              >
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                  <h3 className="font-semibold text-navy">{j.jenis_bantuan}</h3>
                  {j.sumber_dana && (
                    <span className="text-xs text-slate-500">Sumber: {j.sumber_dana}</span>
                  )}
                </div>
                {j.deskripsi && <p className="mt-1 text-sm text-slate-600">{j.deskripsi}</p>}
              </li>
            ))}
          </ul>
        </section>
      )}
    </IsiHalaman>
  )
}

Bansos.layout = bungkusInfografis
