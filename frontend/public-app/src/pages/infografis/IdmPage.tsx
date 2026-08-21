import { StatusMuat } from '@/components/StatusMuat'
import { BatangKategori, KartuAngka } from '@/components/viz/Grafik'
import { useInfografisIdm } from '@/lib/queries'

const DESKRIPSI =
  'Skor IDM desa beserta tiga sub-indeks pembentuknya — Ketahanan Sosial, Ekonomi, dan Ekologi — serta tabel indikator per tahun.'

/** Infografis IDM — PRD 6.7. */
export function IdmPage() {
  const { data, isPending, error } = useInfografisIdm()

  // Skor IDM berkisar 0–1; dikalikan 100 agar terbaca sebagai skala yang
  // lazim dipahami pembaca umum, sementara nilai aslinya tetap ditampilkan.
  const subIndeks = data
    ? [
        { label: 'IKS — Ketahanan Sosial', jumlah: Number(((data.skor.iks ?? 0) * 100).toFixed(2)) },
        { label: 'IKE — Ketahanan Ekonomi', jumlah: Number(((data.skor.ike ?? 0) * 100).toFixed(2)) },
        { label: 'IKL — Ketahanan Ekologi', jumlah: Number(((data.skor.ikl ?? 0) * 100).toFixed(2)) },
      ]
    : []

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      kosong={!data}
      judul="Indeks Desa Membangun (IDM)"
      deskripsi={DESKRIPSI}
    >
      {data && (
        <div className="mx-auto max-w-5xl px-6 py-10">
          <h1 className="font-heading text-2xl font-bold text-navy">
            Indeks Desa Membangun {data.tahun}
          </h1>
          <p className="mt-2 text-slate-600">{DESKRIPSI}</p>

          <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <KartuAngka
              label="Skor IDM"
              nilai={data.skor.idm?.toFixed(4).replace('.', ',') ?? '—'}
            />
            <KartuAngka label="Status Desa" nilai={data.status_idm ?? '—'} />
            <KartuAngka label="Target Status" nilai={data.target.status ?? '—'} />
            <KartuAngka
              label="Skor Perlu Ditambah"
              nilai={data.target.penambahan_dibutuhkan?.toFixed(4).replace('.', ',') ?? '—'}
              keterangan="Menuju target status berikutnya"
            />
          </div>

          <div className="mt-10">
            <BatangKategori judul="Sub-Indeks Pembentuk IDM (skala 0–100)" data={subIndeks} />
          </div>

          {data.indikator.length > 0 && (
            <section className="mt-12">
              <h2 className="font-heading text-lg font-bold text-navy">Tabel Indikator</h2>
              <p className="mt-1 text-sm text-slate-600">
                Kegiatan yang direkomendasikan untuk meningkatkan skor beserta perkiraan
                penambahannya.
              </p>

              <div className="mt-3 overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-navy/10 text-left text-slate-500">
                      <th scope="col" className="py-2 font-medium">No</th>
                      <th scope="col" className="py-2 font-medium">Indikator</th>
                      <th scope="col" className="py-2 text-right font-medium">Skor</th>
                      <th scope="col" className="py-2 font-medium">Kegiatan Rekomendasi</th>
                      <th scope="col" className="py-2 text-right font-medium">Nilai Tambah</th>
                      <th scope="col" className="py-2 font-medium">Pelaksana</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.indikator.map((i) => (
                      <tr key={i.no} className="border-b border-navy/5 align-top">
                        <td className="py-2 tabular-nums text-slate-600">{i.no}</td>
                        <td className="py-2 text-slate-800">{i.nama_indikator}</td>
                        <td className="py-2 text-right tabular-nums text-slate-800">
                          {i.skor?.toFixed(4).replace('.', ',') ?? '—'}
                        </td>
                        <td className="py-2 text-slate-600">{i.kegiatan_rekomendasi ?? '—'}</td>
                        <td className="py-2 text-right tabular-nums text-slate-600">
                          {i.nilai_tambah?.toFixed(4).replace('.', ',') ?? '—'}
                        </td>
                        <td className="py-2 text-slate-600">
                          {i.pihak_pelaksana.length > 0 ? i.pihak_pelaksana.join(', ') : '—'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          )}
        </div>
      )}
    </StatusMuat>
  )
}
