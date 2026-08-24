import { useRef, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, type ApiSuccess } from '@/lib/api'
import { Kartu, Kolom, Input, Pemberitahuan, Tombol } from '@/Components/Admin/Form'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import type { ReactNode } from 'react'

interface Penduduk {
  id: number
  nik: string
  nama: string
  jenis_kelamin: 'L' | 'P'
  tanggal_lahir: string | null
  dusun: string | null
  pekerjaan: string | null
  status_wajib_pilih: boolean
}

interface HasilImpor {
  berhasil: number
  gagal: number
  galat: { baris: number; nik: string; pesan: string }[]
}

const BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

/**
 * CMS Data Penduduk — PRD 5.5 & 10.5.
 *
 * Perhatikan bahwa NIK di seluruh halaman ini tampil TERSAMAR. Membuka NIK
 * utuh adalah tindakan tersendiri yang menuntut izin khusus dan tercatat pada
 * audit trail, jadi tidak disediakan pada tampilan daftar yang mudah tersalin.
 */
export default function PendudukPage() {
  const queryClient = useQueryClient()
  const [cari, setCari] = useState('')
  const [hasilImpor, setHasilImpor] = useState<HasilImpor | null>(null)
  const [galatImpor, setGalatImpor] = useState<string | null>(null)
  const inputBerkas = useRef<HTMLInputElement>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'residents', cari],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Penduduk[]>>('/admin/residents', {
        params: { cari: cari || undefined },
      })
      return { items: r.data.data, meta: r.data.meta }
    },
  })

  const impor = useMutation({
    mutationFn: async (berkas: File) => {
      const form = new FormData()
      form.append('file', berkas)

      const r = await api.post<ApiSuccess<HasilImpor>>('/admin/residents/import', form)
      return r.data.data
    },
    onSuccess: (hasil) => {
      setGalatImpor(null)
      setHasilImpor(hasil)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'residents'] })
      if (inputBerkas.current) inputBerkas.current.value = ''
    },
    onError: (e) => {
      setHasilImpor(null)
      setGalatImpor(
        e instanceof ApiRequestError ? e.message : 'Gagal mengunggah berkas.'
      )
    },
  })

  function kirimBerkas(e: FormEvent) {
    e.preventDefault()
    const berkas = inputBerkas.current?.files?.[0]

    if (berkas) impor.mutate(berkas)
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">Data Penduduk</h1>
        <p className="mt-1 text-sm text-slate-600">
          Data pribadi warga. NIK ditampilkan tersamar dan setiap akses tercatat
          pada log aktivitas sesuai UU Pelindungan Data Pribadi.
        </p>
      </div>

      <Kartu
        judul="Impor Data dari CSV"
        anak={
          <div className="space-y-4">
            <p className="text-sm text-slate-600">
              Unduh template terlebih dahulu, isi menggunakan Excel atau aplikasi
              sejenis, lalu unggah kembali. Baris yang bermasalah akan dilaporkan
              satu per satu tanpa menggagalkan baris lain.
            </p>

            <a
              href={`${BASE_URL}/api/v1/admin/residents/template-csv`}
              className="inline-block rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
              Unduh Template CSV
            </a>

            <form onSubmit={kirimBerkas} className="flex flex-wrap items-end gap-3">
              <div className="min-w-64 flex-1">
                <Kolom label="Berkas CSV" htmlFor="berkas" petunjuk="Maksimal 10 MB.">
                  <input
                    ref={inputBerkas}
                    id="berkas"
                    type="file"
                    accept=".csv,text/csv"
                    required
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-sm"
                  />
                </Kolom>
              </div>
              <Tombol type="submit" disabled={impor.isPending}>
                {impor.isPending ? 'Mengunggah…' : 'Unggah & Impor'}
              </Tombol>
            </form>

            {galatImpor && <Pemberitahuan jenis="galat" pesan={galatImpor} />}

            {hasilImpor && (
              <div className="space-y-3">
                <Pemberitahuan
                  jenis={hasilImpor.gagal === 0 ? 'sukses' : 'galat'}
                  pesan={`Impor selesai: ${hasilImpor.berhasil} baris berhasil, ${hasilImpor.gagal} baris gagal.`}
                />

                {hasilImpor.galat.length > 0 && (
                  <div className="overflow-x-auto rounded-lg border border-slate-200">
                    <table className="w-full text-sm">
                      <caption className="px-3 py-2 text-left text-slate-600">
                        Rincian baris yang gagal
                      </caption>
                      <thead>
                        <tr className="border-y border-slate-200 bg-slate-50 text-left text-slate-500">
                          <th scope="col" className="px-3 py-2 font-medium">Baris</th>
                          <th scope="col" className="px-3 py-2 font-medium">NIK</th>
                          <th scope="col" className="px-3 py-2 font-medium">Alasan</th>
                        </tr>
                      </thead>
                      <tbody>
                        {hasilImpor.galat.map((g, i) => (
                          <tr key={i} className="border-b border-slate-100 last:border-0">
                            <td className="px-3 py-2 tabular-nums text-slate-700">{g.baris}</td>
                            <td className="px-3 py-2 font-mono text-xs text-slate-600">{g.nik}</td>
                            <td className="px-3 py-2 text-slate-700">{g.pesan}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            )}
          </div>
        }
      />

      <Kartu
        judul={`Daftar Penduduk${data?.meta ? ` (${data.meta.total.toLocaleString('id-ID')})` : ''}`}
        anak={
          <div className="space-y-4">
            <Kolom label="Cari nama" htmlFor="cari">
              <Input
                id="cari"
                value={cari}
                onChange={(e) => setCari(e.target.value)}
                placeholder="Ketik nama warga…"
              />
            </Kolom>

            {isPending ? (
              <p className="text-slate-500">Memuat…</p>
            ) : !data?.items.length ? (
              <p className="py-8 text-center text-slate-500">
                {cari
                  ? 'Tidak ada warga dengan nama tersebut.'
                  : 'Belum ada data penduduk. Mulai dengan mengimpor CSV di atas.'}
              </p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 text-left text-slate-500">
                      <th scope="col" className="pb-2 font-medium">NIK</th>
                      <th scope="col" className="pb-2 font-medium">Nama</th>
                      <th scope="col" className="pb-2 font-medium">L/P</th>
                      <th scope="col" className="pb-2 font-medium">Dusun</th>
                      <th scope="col" className="pb-2 font-medium">Pekerjaan</th>
                      <th scope="col" className="pb-2 font-medium">Wajib Pilih</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.items.map((p) => (
                      <tr key={p.id} className="border-b border-slate-100">
                        <td className="py-2 pr-4 font-mono text-xs text-slate-600">{p.nik}</td>
                        <td className="py-2 pr-4 font-medium text-slate-900">{p.nama}</td>
                        <td className="py-2 pr-4 text-slate-700">{p.jenis_kelamin}</td>
                        <td className="py-2 pr-4 text-slate-700">{p.dusun ?? '—'}</td>
                        <td className="py-2 pr-4 text-slate-700">{p.pekerjaan ?? '—'}</td>
                        <td className="py-2 text-slate-700">
                          {p.status_wajib_pilih ? 'Ya' : 'Tidak'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        }
      />
    </div>
  )
}

PendudukPage.layout = (page: ReactNode) => <LayoutAdmin judul="Data Penduduk">{page}</LayoutAdmin>
