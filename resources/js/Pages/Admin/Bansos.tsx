import { useState, type FormEvent } from 'react'
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

interface JenisBantuan {
  id: number
  nama: string
  deskripsi: string | null
  sumber_dana: string | null
  recipients_count: number
}

interface Penerima {
  id: number
  nik: string
  nama: string
  bansos_type_id: number | null
  jenis_bantuan: string | null
  dusun: string | null
  tahun_anggaran: number
  status: string
  nominal: number | null
  nominal_publik: boolean
}

interface PantauPencarian {
  periode: string
  total_pencarian: number
  ip_mencurigakan: { ip: string; jumlah_pencarian: number; gagal: number }[]
}

const FORMAT_RUPIAH = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  maximumFractionDigits: 0,
})

/**
 * CMS Bantuan Sosial — PRD 5.8.
 *
 * Seperti modul Kependudukan, NIK di halaman ini selalu tampil TERSAMAR.
 * Halaman juga memuat panel pemantauan pencarian publik, karena fitur Cek
 * Penerima terbuka bagi siapa pun dan perlu diawasi dari upaya enumerasi.
 */
export default function BansosAdminPage() {
  const [tab, setTab] = useState<'penerima' | 'jenis' | 'pantau'>('penerima')

  return (
    <div className="max-w-4xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">Bantuan Sosial</h1>
        <p className="mt-1 text-sm text-slate-600">
          Kelola data penerima bantuan sosial desa. Data ini tergolong data pribadi —
          setiap pembukaan NIK utuh tercatat pada log aktivitas.
        </p>
      </div>

      <div role="tablist" className="flex gap-1 border-b border-slate-200">
        {(
          [
            ['penerima', 'Data Penerima'],
            ['jenis', 'Jenis Bantuan'],
            ['pantau', 'Pantau Pencarian'],
          ] as const
        ).map(([kunci, label]) => (
          <button
            key={kunci}
            role="tab"
            aria-selected={tab === kunci}
            onClick={() => setTab(kunci)}
            className={`-mb-px border-b-2 px-4 py-2 text-sm transition ${
              tab === kunci
                ? 'border-slate-900 font-medium text-slate-900'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === 'penerima' && <DaftarPenerima />}
      {tab === 'jenis' && <DaftarJenis />}
      {tab === 'pantau' && <PanelPantau />}
    </div>
  )
}

function DaftarPenerima() {
  const queryClient = useQueryClient()
  const [cari, setCari] = useState('')
  const [formTerbuka, setFormTerbuka] = useState(false)
  const [sunting, setSunting] = useState<Penerima | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'bansos', 'penerima', cari],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Penerima[]>>('/admin/bansos/penerima', {
        params: { cari: cari || undefined },
      })
      return r.data.data
    },
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/bansos/penerima/${id}`),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ['admin', 'bansos', 'penerima'] }),
  })

  if (formTerbuka) {
    return (
      <FormPenerima
        penerima={sunting}
        onSelesai={() => {
          setFormTerbuka(false)
          setSunting(null)
          void queryClient.invalidateQueries({ queryKey: ['admin', 'bansos', 'penerima'] })
        }}
      />
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="min-w-64 flex-1">
          <Kolom label="Cari Nama Penerima" htmlFor="cari">
            <Input
              id="cari"
              value={cari}
              onChange={(e) => setCari(e.target.value)}
              placeholder="Ketik nama…"
            />
          </Kolom>
        </div>
        <Tombol
          onClick={() => {
            setSunting(null)
            setFormTerbuka(true)
          }}
        >
          <Plus className="size-4" aria-hidden="true" />
          Tambah Penerima
        </Tombol>
      </div>

      <Kartu
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada data penerima.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-left text-slate-500">
                    <th scope="col" className="pb-2 font-medium">Nama</th>
                    <th scope="col" className="pb-2 font-medium">NIK</th>
                    <th scope="col" className="pb-2 font-medium">Jenis Bantuan</th>
                    <th scope="col" className="pb-2 font-medium">Tahun</th>
                    <th scope="col" className="pb-2 font-medium">Nominal</th>
                    <th scope="col" className="pb-2 font-medium">
                      <span className="sr-only">Aksi</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {data.map((p) => (
                    <tr key={p.id} className="border-b border-slate-100">
                      <td className="py-2.5 pr-4 font-medium text-slate-900">{p.nama}</td>
                      {/* NIK tersamar — daftar seperti ini paling mudah tersalin keluar. */}
                      <td className="py-2.5 pr-4 font-mono text-xs text-slate-600">{p.nik}</td>
                      <td className="py-2.5 pr-4 text-slate-700">{p.jenis_bantuan ?? '—'}</td>
                      <td className="py-2.5 pr-4 tabular-nums text-slate-700">
                        {p.tahun_anggaran}
                      </td>
                      <td className="py-2.5 pr-4 tabular-nums text-slate-700">
                        {p.nominal === null ? '—' : FORMAT_RUPIAH.format(p.nominal)}
                        {p.nominal !== null && !p.nominal_publik && (
                          <span className="ml-1 text-xs text-slate-400">(privat)</span>
                        )}
                      </td>
                      <td className="py-2.5">
                        <AksiBaris
                          nama={`data penerima “${p.nama}”`}
                          onSunting={() => {
                            setSunting(p)
                            setFormTerbuka(true)
                          }}
                          onHapus={() => hapus.mutate(p.id)}
                          sedangProses={hapus.isPending}
                        />
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

function FormPenerima({
  penerima,
  onSelesai,
}: {
  penerima: Penerima | null
  onSelesai: () => void
}) {
  const [form, setForm] = useState({
    bansos_type_id: penerima?.bansos_type_id ? String(penerima.bansos_type_id) : '',
    nama: penerima?.nama ?? '',
    // NIK tidak pernah dikirim ke daftar dalam bentuk utuh, jadi kolomnya
    // dikosongkan saat menyunting: diisi hanya bila NIK-nya memang diperbaiki.
    nik: '',
    tahun_anggaran: String(penerima?.tahun_anggaran ?? new Date().getFullYear()),
    nominal: penerima?.nominal != null ? String(penerima.nominal) : '',
    nominal_publik: penerima?.nominal_publik ?? false,
  })
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const { data: jenis } = useQuery({
    queryKey: ['admin', 'bansos', 'jenis'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<JenisBantuan[]>>('/admin/bansos/jenis')
      return r.data.data
    },
  })

  const simpan = useMutation({
    mutationFn: () => {
      const isi = {
        ...form,
        nominal: form.nominal || null,
        // NIK kosong saat menyunting berarti "biarkan seperti semula".
        ...(penerima && !form.nik ? { nik: undefined } : {}),
      }

      return penerima
        ? api.put(`/admin/bansos/penerima/${penerima.id}`, isi)
        : api.post('/admin/bansos/penerima', isi)
    },
    onSuccess: onSelesai,
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  function kirim(e: FormEvent) {
    e.preventDefault()
    simpan.mutate()
  }

  return (
    <form onSubmit={kirim} className="space-y-6">
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-semibold text-slate-900">
          {penerima ? `Ubah Penerima: ${penerima.nama}` : 'Tambah Penerima Bantuan'}
        </h2>
        <Tombol type="button" variasi="sekunder" onClick={onSelesai}>
          Batal
        </Tombol>
      </div>

      {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

      <Kartu
        anak={
          <div className="grid gap-4 sm:grid-cols-2">
            <Kolom
              label="Jenis Bantuan"
              htmlFor="bansos_type_id"
              galat={galat?.fieldError('bansos_type_id')}
            >
              <Pilihan
                id="bansos_type_id"
                value={form.bansos_type_id}
                onChange={(v) => setForm({ ...form, bansos_type_id: v })}
                placeholder="Pilih jenis bantuan…"
                galat={galat?.fieldError('bansos_type_id')}
                options={(jenis ?? []).map((j) => ({ value: String(j.id), label: j.nama }))}
              />
            </Kolom>

            <Kolom label="Nama Penerima" htmlFor="nama" galat={galat?.fieldError('nama')}>
              <Input
                id="nama"
                required
                value={form.nama}
                onChange={(e) => setForm({ ...form, nama: e.target.value })}
                galat={galat?.fieldError('nama')}
              />
            </Kolom>

            <Kolom
              label="NIK"
              htmlFor="nik"
              petunjuk="16 digit. Disimpan terenkripsi dan tidak pernah ditampilkan utuh di daftar."
              galat={galat?.fieldError('nik')}
            >
              <Input
                id="nik"
                required
                inputMode="numeric"
                maxLength={16}
                value={form.nik}
                onChange={(e) =>
                  setForm({ ...form, nik: e.target.value.replace(/\D/g, '').slice(0, 16) })
                }
                galat={galat?.fieldError('nik')}
              />
            </Kolom>

            <Kolom
              label="Tahun Anggaran"
              htmlFor="tahun_anggaran"
              galat={galat?.fieldError('tahun_anggaran')}
            >
              <Input
                id="tahun_anggaran"
                type="number"
                required
                value={form.tahun_anggaran}
                onChange={(e) => setForm({ ...form, tahun_anggaran: e.target.value })}
                galat={galat?.fieldError('tahun_anggaran')}
              />
            </Kolom>

            <Kolom label="Nominal (Rp)" htmlFor="nominal" galat={galat?.fieldError('nominal')}>
              <Input
                id="nominal"
                type="number"
                min={0}
                value={form.nominal}
                onChange={(e) => setForm({ ...form, nominal: e.target.value })}
                galat={galat?.fieldError('nominal')}
              />
            </Kolom>

            <div className="flex items-end">
              <label className="flex items-center gap-2 text-sm text-slate-700">
                <input
                  type="checkbox"
                  checked={form.nominal_publik}
                  onChange={(e) => setForm({ ...form, nominal_publik: e.target.checked })}
                  className="size-4 rounded border-slate-300"
                />
                Tampilkan nominal ke publik
              </label>
            </div>
          </div>
        }
      />

      <Tombol type="submit" disabled={simpan.isPending}>
        {simpan.isPending ? 'Menyimpan…' : 'Simpan Penerima'}
      </Tombol>
    </form>
  )
}

function DaftarJenis() {
  const queryClient = useQueryClient()
  const kosong = { nama: '', deskripsi: '', sumber_dana: '' }
  const [form, setForm] = useState(kosong)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [sunting, setSunting] = useState<JenisBantuan | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'bansos', 'jenis'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<JenisBantuan[]>>('/admin/bansos/jenis')
      return r.data.data
    },
  })

  const simpan = useMutation({
    mutationFn: () =>
      sunting
        ? api.put(`/admin/bansos/jenis/${sunting.id}`, form)
        : api.post('/admin/bansos/jenis', form),
    onSuccess: () => {
      setForm(kosong)
      setSunting(null)
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'bansos', 'jenis'] })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/bansos/jenis/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'bansos', 'jenis'] }),
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menghapus.', 0)),
  })

  return (
    <div className="space-y-6">
      <Kartu
        judul={sunting ? `Ubah Jenis: ${sunting.nama}` : 'Tambah Jenis Bantuan'}
        anak={
          <form
            onSubmit={(e) => {
              e.preventDefault()
              simpan.mutate()
            }}
            className="space-y-4"
          >
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

            <div className="grid gap-4 sm:grid-cols-3">
              <Kolom label="Nama Bantuan" htmlFor="nama-jenis" galat={galat?.fieldError('nama')}>
                <Input
                  id="nama-jenis"
                  required
                  placeholder="mis. BLT Dana Desa"
                  value={form.nama}
                  onChange={(e) => setForm({ ...form, nama: e.target.value })}
                  galat={galat?.fieldError('nama')}
                />
              </Kolom>

              <Kolom label="Sumber Dana" htmlFor="sumber_dana">
                <Input
                  id="sumber_dana"
                  placeholder="mis. Dana Desa / APBN"
                  value={form.sumber_dana}
                  onChange={(e) => setForm({ ...form, sumber_dana: e.target.value })}
                />
              </Kolom>

              <Kolom label="Deskripsi" htmlFor="deskripsi">
                <Input
                  id="deskripsi"
                  value={form.deskripsi}
                  onChange={(e) => setForm({ ...form, deskripsi: e.target.value })}
                />
              </Kolom>
            </div>

            <div className="flex flex-wrap gap-2">
              <Tombol type="submit" disabled={simpan.isPending}>
                {simpan.isPending ? 'Menyimpan…' : sunting ? 'Simpan Perubahan' : 'Tambah'}
              </Tombol>

              {sunting && (
                <Tombol
                  type="button"
                  variasi="sekunder"
                  onClick={() => {
                    setSunting(null)
                    setForm(kosong)
                    setGalat(null)
                  }}
                >
                  Batal
                </Tombol>
              )}
            </div>
          </form>
        }
      />

      <Kartu
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada jenis bantuan.</p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {data.map((j) => (
                <li key={j.id} className="flex items-center justify-between gap-4 py-3">
                  <div className="min-w-0">
                    <p className="truncate font-medium text-slate-900">{j.nama}</p>
                    <p className="truncate text-sm text-slate-500">
                      {[j.sumber_dana, `${j.recipients_count} penerima`]
                        .filter(Boolean)
                        .join(' · ')}
                    </p>
                  </div>
                  <AksiBaris
                    nama={
                      j.recipients_count > 0
                        ? `jenis bantuan “${j.nama}” beserta ${j.recipients_count} penerimanya`
                        : `jenis bantuan “${j.nama}”`
                    }
                    onSunting={() => {
                      setSunting(j)
                      setForm({
                        nama: j.nama,
                        deskripsi: j.deskripsi ?? '',
                        sumber_dana: j.sumber_dana ?? '',
                      })
                      setGalat(null)
                      window.scrollTo({ top: 0, behavior: 'smooth' })
                    }}
                    onHapus={() => hapus.mutate(j.id)}
                    sedangProses={hapus.isPending}
                  />
                </li>
              ))}
            </ul>
          )
        }
      />
    </div>
  )
}

/**
 * Panel pemantauan pencarian publik — PRD 6.6 & 15.
 *
 * Satu IP dengan puluhan pencarian gagal adalah pola enumerasi, bukan warga
 * yang mengecek statusnya sendiri.
 */
function PanelPantau() {
  const { data, isPending } = useQuery({
    queryKey: ['admin', 'bansos', 'pantau'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<PantauPencarian>>('/admin/bansos/pantau-pencarian')
      return r.data.data
    },
  })

  if (isPending) return <p className="text-slate-500">Memuat…</p>

  return (
    <div className="space-y-6">
      <Kartu
        judul="Aktivitas Pencarian Publik"
        anak={
          <div>
            <p className="text-sm text-slate-600">
              Ringkasan {data?.periode}. Formulir Cek Penerima sudah ditarik dari situs, jadi
              angka di bawah hanya terisi bila endpoint lama masih dipanggil dari luar —
              lonjakan dari satu sumber justru perlu diperhatikan.
            </p>

            <p className="mt-4 text-2xl font-semibold text-slate-900">
              {data?.total_pencarian.toLocaleString('id-ID')}
              <span className="ml-2 text-base font-normal text-slate-500">pencarian</span>
            </p>
          </div>
        }
      />

      <Kartu
        judul="Sumber Perlu Diperhatikan"
        anak={
          !data?.ip_mencurigakan.length ? (
            <p className="py-6 text-center text-sm text-slate-500">
              Tidak ada pola pencarian mencurigakan pada periode ini.
            </p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-left text-slate-500">
                    <th scope="col" className="pb-2 font-medium">Sumber (ter-hash)</th>
                    <th scope="col" className="pb-2 text-right font-medium">Pencarian</th>
                    <th scope="col" className="pb-2 text-right font-medium">Gagal</th>
                  </tr>
                </thead>
                <tbody>
                  {data.ip_mencurigakan.map((b) => (
                    <tr key={b.ip} className="border-b border-slate-100">
                      <td className="py-2.5 font-mono text-xs text-slate-600">{b.ip}</td>
                      <td className="py-2.5 text-right tabular-nums text-slate-800">
                        {b.jumlah_pencarian}
                      </td>
                      <td className="py-2.5 text-right tabular-nums text-slate-800">{b.gagal}</td>
                    </tr>
                  ))}
                </tbody>
              </table>

              <p className="mt-4 text-xs text-slate-500">
                Alamat IP tidak disimpan — yang tercatat hanya sidik hash-nya, cukup untuk
                membedakan satu sumber dari yang lain tanpa menyimpan identitas jaringan.
              </p>
            </div>
          )
        }
      />
    </div>
  )
}

BansosAdminPage.layout = (page: ReactNode) => <LayoutAdmin judul="Bantuan Sosial">{page}</LayoutAdmin>
