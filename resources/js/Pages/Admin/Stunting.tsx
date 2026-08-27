import { useState, type FormEvent, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, type ApiSuccess } from '@/lib/api'
import { Kartu, Kolom, Input, Pemberitahuan, TextArea, Tombol } from '@/Components/Admin/Form'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'

interface CatatanStunting {
  id: number
  periode: string
  dusun_id: number | null
  dusun: { id: number; nama: string } | null
  jumlah_balita_diukur: number
  jumlah_kasus_stunting: number
  persentase_prevalensi: number | null
  keterangan: string | null
}

interface Dusun {
  id: number
  nama: string
}

const FORM_KOSONG = {
  periode: '',
  dusun_id: '',
  jumlah_balita_diukur: '',
  jumlah_kasus_stunting: '',
  keterangan: '',
}

/**
 * CMS Stunting — PRD 5.7 & 6.5.
 *
 * Data prevalensi stunting per periode. Dusun boleh dikosongkan untuk mencatat
 * angka tingkat desa; diisi bila datanya dirinci per dusun.
 */
export default function StuntingAdminPage() {
  const queryClient = useQueryClient()
  const [form, setForm] = useState(FORM_KOSONG)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [sukses, setSukses] = useState<string | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'stunting'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<CatatanStunting[]>>('/admin/stunting')
      return r.data.data
    },
  })

  const { data: dusuns } = useQuery({
    queryKey: ['admin', 'stunting', 'dusuns'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Dusun[]>>('/admin/stunting/dusuns')
      return r.data.data
    },
  })

  const simpan = useMutation({
    mutationFn: () =>
      api.post('/admin/stunting', {
        periode: form.periode,
        dusun_id: form.dusun_id ? Number(form.dusun_id) : null,
        jumlah_balita_diukur: Number(form.jumlah_balita_diukur),
        jumlah_kasus_stunting: Number(form.jumlah_kasus_stunting),
        keterangan: form.keterangan || null,
      }),
    onSuccess: () => {
      setForm(FORM_KOSONG)
      setGalat(null)
      setSukses('Data stunting berhasil disimpan.')
      void queryClient.invalidateQueries({ queryKey: ['admin', 'stunting'] })
    },
    onError: (e) => {
      setSukses(null)
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0))
    },
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/stunting/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'stunting'] }),
  })

  function kirim(e: FormEvent) {
    e.preventDefault()
    simpan.mutate()
  }

  return (
    <div className="max-w-4xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">Data Stunting</h1>
        <p className="mt-1 text-sm text-slate-600">
          Catat jumlah balita yang diukur dan kasus stunting per periode. Prevalensi
          dihitung otomatis. Menyimpan periode &amp; dusun yang sama akan memperbarui data
          sebelumnya.
        </p>
      </div>

      <Kartu
        judul="Tambah / Perbarui Data"
        anak={
          <form onSubmit={kirim} className="space-y-4">
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}
            {sukses && <Pemberitahuan jenis="sukses" pesan={sukses} />}

            <div className="grid gap-4 sm:grid-cols-2">
              <Kolom
                label="Periode"
                htmlFor="periode"
                petunjuk="Format YYYY-MM (mis. 2026-01)."
                galat={galat?.fieldError('periode')}
              >
                <Input
                  id="periode"
                  type="month"
                  required
                  value={form.periode}
                  onChange={(e) => setForm({ ...form, periode: e.target.value })}
                  galat={galat?.fieldError('periode')}
                />
              </Kolom>

              <Kolom
                label="Dusun"
                htmlFor="dusun_id"
                petunjuk="Kosongkan untuk angka tingkat desa."
                galat={galat?.fieldError('dusun_id')}
              >
                <select
                  id="dusun_id"
                  value={form.dusun_id}
                  onChange={(e) => setForm({ ...form, dusun_id: e.target.value })}
                  className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20"
                >
                  <option value="">Seluruh desa</option>
                  {dusuns?.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.nama}
                    </option>
                  ))}
                </select>
              </Kolom>

              <Kolom
                label="Jumlah Balita Diukur"
                htmlFor="jumlah_balita_diukur"
                galat={galat?.fieldError('jumlah_balita_diukur')}
              >
                <Input
                  id="jumlah_balita_diukur"
                  type="number"
                  min={0}
                  required
                  value={form.jumlah_balita_diukur}
                  onChange={(e) => setForm({ ...form, jumlah_balita_diukur: e.target.value })}
                  galat={galat?.fieldError('jumlah_balita_diukur')}
                />
              </Kolom>

              <Kolom
                label="Jumlah Kasus Stunting"
                htmlFor="jumlah_kasus_stunting"
                petunjuk="Tidak boleh melebihi jumlah balita diukur."
                galat={galat?.fieldError('jumlah_kasus_stunting')}
              >
                <Input
                  id="jumlah_kasus_stunting"
                  type="number"
                  min={0}
                  required
                  value={form.jumlah_kasus_stunting}
                  onChange={(e) => setForm({ ...form, jumlah_kasus_stunting: e.target.value })}
                  galat={galat?.fieldError('jumlah_kasus_stunting')}
                />
              </Kolom>
            </div>

            <Kolom label="Keterangan" htmlFor="keterangan" galat={galat?.fieldError('keterangan')}>
              <TextArea
                id="keterangan"
                rows={2}
                value={form.keterangan}
                onChange={(e) => setForm({ ...form, keterangan: e.target.value })}
                galat={galat?.fieldError('keterangan')}
              />
            </Kolom>

            <Tombol type="submit" disabled={simpan.isPending}>
              {simpan.isPending ? 'Menyimpan…' : 'Simpan Data'}
            </Tombol>
          </form>
        }
      />

      <Kartu
        judul="Riwayat Data Stunting"
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada data stunting.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-left text-slate-500">
                    <th scope="col" className="pb-2 font-medium">Periode</th>
                    <th scope="col" className="pb-2 font-medium">Dusun</th>
                    <th scope="col" className="pb-2 text-right font-medium">Balita Diukur</th>
                    <th scope="col" className="pb-2 text-right font-medium">Kasus</th>
                    <th scope="col" className="pb-2 text-right font-medium">Prevalensi</th>
                    <th scope="col" className="pb-2 font-medium">
                      <span className="sr-only">Aksi</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {data.map((c) => (
                    <tr key={c.id} className="border-b border-slate-100">
                      <td className="py-2.5 pr-4 font-medium text-slate-900">{c.periode}</td>
                      <td className="py-2.5 pr-4 text-slate-700">{c.dusun?.nama ?? 'Seluruh desa'}</td>
                      <td className="py-2.5 pr-4 text-right tabular-nums text-slate-700">
                        {c.jumlah_balita_diukur.toLocaleString('id-ID')}
                      </td>
                      <td className="py-2.5 pr-4 text-right tabular-nums text-slate-700">
                        {c.jumlah_kasus_stunting.toLocaleString('id-ID')}
                      </td>
                      <td className="py-2.5 pr-4 text-right tabular-nums text-slate-700">
                        {c.persentase_prevalensi === null
                          ? '—'
                          : `${c.persentase_prevalensi.toFixed(1)}%`}
                      </td>
                      <td className="py-2.5 text-right">
                        <button
                          onClick={() => {
                            if (confirm(`Hapus data stunting periode ${c.periode}?`))
                              hapus.mutate(c.id)
                          }}
                          className="text-red-600 hover:underline"
                        >
                          Hapus
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )
        }
      />
    </div>
  )
}

StuntingAdminPage.layout = (page: ReactNode) => (
  <LayoutAdmin judul="Data Stunting">{page}</LayoutAdmin>
)
