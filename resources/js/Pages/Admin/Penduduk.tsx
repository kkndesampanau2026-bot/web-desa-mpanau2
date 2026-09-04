import { useRef, useState, type FormEvent } from 'react'
import { Plus } from 'lucide-react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, type ApiSuccess } from '@/lib/api'
import {
  AksiBaris,
  Kartu,
  Kolom,
  Input,
  Pemberitahuan,
  Pilihan,
  Tombol,
} from '@/Components/Admin/Form'
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

interface Dusun {
  id: number
  nama: string
}

/**
 * Pilihan formulir datang dari server.
 *
 * Kolom pendidikan, agama, hubungan KK, status perkawinan, dan domisili adalah
 * ENUM di basis data. Menyalin daftarnya ke berkas ini berarti dua sumber yang
 * cepat atau lambat menyimpang — dan menyimpangnya baru ketahuan sebagai galat
 * 500 saat operator menekan Simpan.
 */
interface OpsiPenduduk {
  dusun: Dusun[]
  hubungan_kk: string[]
  pendidikan: string[]
  perkawinan: string[]
  agama: string[]
  domisili: string[]
}

/** Nilai formulir — semuanya string karena berasal dari elemen input. */
const FORM_KOSONG = {
  nik: '',
  no_kk: '',
  nama: '',
  jenis_kelamin: 'L',
  tanggal_lahir: '',
  dusun_id: '',
  status_hubungan_kk: '',
  pendidikan_terakhir: '',
  pekerjaan: '',
  status_perkawinan: '',
  agama: '',
  status_domisili: '',
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

  const [formTerbuka, setFormTerbuka] = useState(false)
  const [sunting, setSunting] = useState<{ id: number; nama: string } | null>(null)
  const [form, setForm] = useState(FORM_KOSONG)
  const [galatForm, setGalatForm] = useState<ApiRequestError | null>(null)
  const [galatMuat, setGalatMuat] = useState<string | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'residents', cari],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Penduduk[]>>('/admin/residents', {
        params: { cari: cari || undefined },
      })
      return { items: r.data.data, meta: r.data.meta }
    },
  })

  const { data: opsi } = useQuery({
    queryKey: ['admin', 'residents', 'opsi'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<OpsiPenduduk>>('/admin/residents/opsi')
      return r.data.data
    },
  })

  /** Membentuk daftar pilihan; aman dipanggil sebelum opsi selesai dimuat. */
  const pilihan = (daftar?: string[]) => (daftar ?? []).map((v) => ({ value: v, label: v }))

  const simpan = useMutation({
    mutationFn: () => {
      // Kolom opsional yang dibiarkan kosong dikirim sebagai null, bukan
      // string kosong: `nullable` pada validator meloloskan null, sedangkan
      // '' menembus aturan `Rule::in(...)` dan ditolak.
      const isi = Object.fromEntries(
        Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]),
      )

      return sunting
        ? api.put(`/admin/residents/${sunting.id}`, isi)
        : api.post('/admin/residents', isi)
    },
    onSuccess: () => {
      tutupForm()
      void queryClient.invalidateQueries({ queryKey: ['admin', 'residents'] })
    },
    onError: (e) =>
      setGalatForm(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/residents/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'residents'] }),
  })

  /**
   * Memuat satu baris untuk disunting.
   *
   * Daftar hanya memuat NIK tersamar, jadi nilai lengkapnya harus diambil dari
   * endpoint detail — dan endpoint itu menuntut izin `view-population-pii`
   * serta mencatat pembukaannya pada audit trail. Operator tanpa izin itu
   * boleh mengelola data lain, tetapi tidak boleh menyunting baris ini tanpa
   * jejak; karena itu galatnya dijelaskan apa adanya, bukan dibiarkan senyap.
   */
  async function mulaiSunting(p: Penduduk) {
    setGalatMuat(null)

    try {
      const r = await api.get<ApiSuccess<Record<string, unknown>>>(`/admin/residents/${p.id}`)
      const d = r.data.data

      const teks = (kunci: string) => (d[kunci] == null ? '' : String(d[kunci]))

      setForm({
        nik: teks('nik'),
        no_kk: teks('no_kk'),
        nama: teks('nama'),
        jenis_kelamin: teks('jenis_kelamin') || 'L',
        tanggal_lahir: teks('tanggal_lahir').slice(0, 10),
        dusun_id: teks('dusun_id'),
        status_hubungan_kk: teks('status_hubungan_kk'),
        pendidikan_terakhir: teks('pendidikan_terakhir'),
        pekerjaan: teks('pekerjaan'),
        status_perkawinan: teks('status_perkawinan'),
        agama: teks('agama'),
        status_domisili: teks('status_domisili'),
      })
      setSunting({ id: p.id, nama: p.nama })
      setGalatForm(null)
      setFormTerbuka(true)
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (e) {
      setGalatMuat(
        e instanceof ApiRequestError && e.status === 403
          ? 'Menyunting data warga menuntut izin melihat data pribadi (view-population-pii). Hubungi Admin Utama.'
          : 'Gagal memuat data warga.',
      )
    }
  }

  function tutupForm() {
    setFormTerbuka(false)
    setSunting(null)
    setForm(FORM_KOSONG)
    setGalatForm(null)
  }

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

      {galatMuat && <Pemberitahuan jenis="galat" pesan={galatMuat} />}

      {formTerbuka && (
        <Kartu
          judul={sunting ? `Ubah Data: ${sunting.nama}` : 'Tambah Penduduk'}
          anak={
            <form
              onSubmit={(e: FormEvent) => {
                e.preventDefault()
                simpan.mutate()
              }}
              className="space-y-4"
            >
              {galatForm && !galatForm.errors && (
                <Pemberitahuan jenis="galat" pesan={galatForm.message} />
              )}

              <div className="grid gap-4 sm:grid-cols-2">
                <Kolom label="NIK" htmlFor="nik" petunjuk="16 digit angka." galat={galatForm?.fieldError('nik')}>
                  <Input
                    id="nik"
                    required
                    inputMode="numeric"
                    maxLength={16}
                    value={form.nik}
                    onChange={(e) => setForm({ ...form, nik: e.target.value.replace(/\D/g, '') })}
                    galat={galatForm?.fieldError('nik')}
                  />
                </Kolom>

                <Kolom label="No. Kartu Keluarga" htmlFor="no_kk" galat={galatForm?.fieldError('no_kk')}>
                  <Input
                    id="no_kk"
                    inputMode="numeric"
                    maxLength={16}
                    value={form.no_kk}
                    onChange={(e) => setForm({ ...form, no_kk: e.target.value.replace(/\D/g, '') })}
                    galat={galatForm?.fieldError('no_kk')}
                  />
                </Kolom>

                <Kolom label="Nama Lengkap" htmlFor="nama" galat={galatForm?.fieldError('nama')}>
                  <Input
                    id="nama"
                    required
                    value={form.nama}
                    onChange={(e) => setForm({ ...form, nama: e.target.value })}
                    galat={galatForm?.fieldError('nama')}
                  />
                </Kolom>

                <Kolom label="Jenis Kelamin" htmlFor="jenis_kelamin">
                  <Pilihan
                    id="jenis_kelamin"
                    value={form.jenis_kelamin}
                    onChange={(v) => setForm({ ...form, jenis_kelamin: v })}
                    options={[
                      { value: 'L', label: 'Laki-laki' },
                      { value: 'P', label: 'Perempuan' },
                    ]}
                  />
                </Kolom>

                <Kolom label="Tanggal Lahir" htmlFor="tanggal_lahir" galat={galatForm?.fieldError('tanggal_lahir')}>
                  <Input
                    id="tanggal_lahir"
                    type="date"
                    value={form.tanggal_lahir}
                    onChange={(e) => setForm({ ...form, tanggal_lahir: e.target.value })}
                    galat={galatForm?.fieldError('tanggal_lahir')}
                  />
                </Kolom>

                <Kolom label="Dusun" htmlFor="dusun_id">
                  <Pilihan
                    id="dusun_id"
                    value={form.dusun_id}
                    onChange={(v) => setForm({ ...form, dusun_id: v })}
                    placeholder="Tidak ditentukan"
                    options={(opsi?.dusun ?? []).map((d) => ({
                      value: String(d.id),
                      label: d.nama,
                    }))}
                  />
                </Kolom>

                <Kolom label="Hubungan dalam KK" htmlFor="status_hubungan_kk">
                  <Pilihan
                    id="status_hubungan_kk"
                    value={form.status_hubungan_kk}
                    onChange={(v) => setForm({ ...form, status_hubungan_kk: v })}
                    placeholder="Tidak ditentukan"
                    options={pilihan(opsi?.hubungan_kk)}
                  />
                </Kolom>

                <Kolom label="Status Perkawinan" htmlFor="status_perkawinan">
                  <Pilihan
                    id="status_perkawinan"
                    value={form.status_perkawinan}
                    onChange={(v) => setForm({ ...form, status_perkawinan: v })}
                    placeholder="Tidak ditentukan"
                    options={pilihan(opsi?.perkawinan)}
                  />
                </Kolom>

                {/*
                  Pilihan, bukan ketikan bebas: kolomnya ENUM di basis data,
                  sehingga jenjang di luar daftar baku Dapodik/BPS ("PAUD",
                  misalnya) ditolak MySQL saat disimpan.
                */}
                <Kolom
                  label="Pendidikan Terakhir"
                  htmlFor="pendidikan_terakhir"
                  galat={galatForm?.fieldError('pendidikan_terakhir')}
                >
                  <Pilihan
                    id="pendidikan_terakhir"
                    value={form.pendidikan_terakhir}
                    onChange={(v) => setForm({ ...form, pendidikan_terakhir: v })}
                    placeholder="Tidak ditentukan"
                    options={pilihan(opsi?.pendidikan)}
                  />
                </Kolom>

                <Kolom label="Pekerjaan" htmlFor="pekerjaan">
                  <Input
                    id="pekerjaan"
                    value={form.pekerjaan}
                    onChange={(e) => setForm({ ...form, pekerjaan: e.target.value })}
                  />
                </Kolom>

                <Kolom label="Agama" htmlFor="agama" galat={galatForm?.fieldError('agama')}>
                  <Pilihan
                    id="agama"
                    value={form.agama}
                    onChange={(v) => setForm({ ...form, agama: v })}
                    placeholder="Tidak ditentukan"
                    options={pilihan(opsi?.agama)}
                  />
                </Kolom>

                <Kolom label="Status Domisili" htmlFor="status_domisili">
                  <Pilihan
                    id="status_domisili"
                    value={form.status_domisili}
                    onChange={(v) => setForm({ ...form, status_domisili: v })}
                    placeholder="Tidak ditentukan"
                    options={pilihan(opsi?.domisili)}
                  />
                </Kolom>
              </div>

              <div className="flex flex-wrap gap-2">
                <Tombol type="submit" disabled={simpan.isPending}>
                  {simpan.isPending ? 'Menyimpan…' : sunting ? 'Simpan Perubahan' : 'Tambah'}
                </Tombol>
                <Tombol type="button" variasi="sekunder" onClick={tutupForm}>
                  Batal
                </Tombol>
              </div>
            </form>
          }
        />
      )}

      <Kartu
        judul={`Daftar Penduduk${data?.meta ? ` (${data.meta.total.toLocaleString('id-ID')})` : ''}`}
        anak={
          <div className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
              <div className="min-w-56 flex-1">
                <Kolom label="Cari nama" htmlFor="cari">
                  <Input
                    id="cari"
                    value={cari}
                    onChange={(e) => setCari(e.target.value)}
                    placeholder="Ketik nama warga…"
                  />
                </Kolom>
              </div>

              {!formTerbuka && (
                <Tombol
                  type="button"
                  onClick={() => {
                    setSunting(null)
                    setForm(FORM_KOSONG)
                    setGalatForm(null)
                    setGalatMuat(null)
                    setFormTerbuka(true)
                  }}
                >
                  <Plus className="size-4" aria-hidden="true" />
                  Tambah Penduduk
                </Tombol>
              )}
            </div>

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
                      <th scope="col" className="pb-2 font-medium">
                        <span className="sr-only">Aksi</span>
                      </th>
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
                        <td className="py-2 pr-4 text-slate-700">
                          {p.status_wajib_pilih ? 'Ya' : 'Tidak'}
                        </td>
                        <td className="py-2">
                          <AksiBaris
                            nama={`data warga ${p.nama}`}
                            onSunting={() => void mulaiSunting(p)}
                            onHapus={() => hapus.mutate(p.id)}
                            sedangProses={hapus.isPending}
                          />
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
