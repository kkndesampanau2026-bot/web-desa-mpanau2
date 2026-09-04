import { useState, type FormEvent, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, type ApiSuccess } from '@/lib/api'
import {
  AksiBaris,
  Input,
  Kartu,
  Kolom,
  Pemberitahuan,
  Pilihan,
  TextArea,
  Tombol,
} from '@/Components/Admin/Form'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import { formatRupiah } from '@/lib/format'

const KELOMPOK = [
  'Pendapatan',
  'Belanja',
  'Pembiayaan-Penerimaan',
  'Pembiayaan-Pengeluaran',
] as const

interface TahunAnggaran {
  id: number
  tahun: number
  status: 'berjalan' | 'ditutup'
  publikasikan: boolean
  total_anggaran: number | null
}

interface Kategori {
  id: number
  kelompok: string
  nama: string
  kode: string | null
  urutan_tampil: number
}

interface Item {
  id: number
  budget_year_id: number
  budget_category_id: number
  nama_item: string
  jumlah_anggaran: number
  jumlah_realisasi: number | null
  keterangan: string | null
  kategori: Kategori | null
}

/**
 * CMS APBDes — PRD 5.6 & 6.4.
 *
 * Dibagi tiga tab yang saling bergantung: tahun anggaran menaungi item, dan
 * setiap item merujuk satu kategori. Karena itu kategori serta minimal satu
 * tahun harus ada sebelum rincian item dapat diisi.
 */
export default function ApbdesAdminPage() {
  const [tab, setTab] = useState<'item' | 'tahun' | 'kategori'>('item')

  return (
    <div className="max-w-5xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">APBDes</h1>
        <p className="mt-1 text-sm text-slate-600">
          Anggaran Pendapatan dan Belanja Desa. Kelola tahun anggaran, master kategori, dan
          rincian item beserta realisasinya.
        </p>
      </div>

      <div role="tablist" className="flex gap-1 border-b border-slate-200">
        {(
          [
            ['item', 'Rincian Item'],
            ['tahun', 'Tahun Anggaran'],
            ['kategori', 'Kategori'],
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

      {tab === 'item' && <TabItem />}
      {tab === 'tahun' && <TabTahun />}
      {tab === 'kategori' && <TabKategori />}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Tab: Tahun Anggaran
// ---------------------------------------------------------------------------

function TabTahun() {
  const queryClient = useQueryClient()
  const [tahun, setTahun] = useState(String(new Date().getFullYear()))
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'apbdes', 'tahun'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<TahunAnggaran[]>>('/admin/apbdes/tahun')
      return r.data.data
    },
  })

  const tambah = useMutation({
    mutationFn: () => api.post('/admin/apbdes/tahun', { tahun: Number(tahun) }),
    onSuccess: () => {
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'apbdes', 'tahun'] })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const ubah = useMutation({
    mutationFn: (payload: { id: number; status?: string; publikasikan?: boolean }) =>
      api.put(`/admin/apbdes/tahun/${payload.id}`, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'apbdes', 'tahun'] }),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/apbdes/tahun/${id}`),
    onSuccess: () => {
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'apbdes', 'tahun'] })
    },
    // Server menolak tahun yang masih berisi rincian; pesannya diteruskan apa
    // adanya karena ia menyebutkan berapa rincian yang harus dibereskan dulu.
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menghapus.', 0)),
  })

  return (
    <div className="space-y-6">
      <Kartu
        judul="Tambah Tahun Anggaran"
        anak={
          <form
            onSubmit={(e) => {
              e.preventDefault()
              tambah.mutate()
            }}
            className="flex flex-wrap items-end gap-3"
          >
            <div className="min-w-48">
              <Kolom label="Tahun" htmlFor="tahun-baru" galat={galat?.fieldError('tahun')}>
                <Input
                  id="tahun-baru"
                  type="number"
                  min={2000}
                  max={2100}
                  required
                  value={tahun}
                  onChange={(e) => setTahun(e.target.value)}
                  galat={galat?.fieldError('tahun')}
                />
              </Kolom>
            </div>
            <Tombol type="submit" disabled={tambah.isPending}>
              {tambah.isPending ? 'Menyimpan…' : 'Tambah'}
            </Tombol>
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}
          </form>
        }
      />

      <Kartu
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada tahun anggaran.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-left text-slate-500">
                    <th scope="col" className="pb-2 font-medium">Tahun</th>
                    <th scope="col" className="pb-2 text-right font-medium">Total Anggaran</th>
                    <th scope="col" className="pb-2 font-medium">Status</th>
                    <th scope="col" className="pb-2 font-medium">Publik</th>
                    <th scope="col" className="pb-2 font-medium">
                      <span className="sr-only">Aksi</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {data.map((t) => (
                    <tr key={t.id} className="border-b border-slate-100">
                      <td className="py-2.5 pr-4 font-medium text-slate-900">{t.tahun}</td>
                      <td className="py-2.5 pr-4 text-right tabular-nums text-slate-700">
                        {formatRupiah(t.total_anggaran ?? 0)}
                      </td>
                      <td className="py-2.5 pr-4">
                        <Pilihan
                          id={`status-tahun-${t.id}`}
                          value={t.status}
                          onChange={(v) => ubah.mutate({ id: t.id, status: v })}
                          options={[
                            { value: 'berjalan', label: 'Berjalan' },
                            { value: 'ditutup', label: 'Ditutup' },
                          ]}
                        />
                      </td>
                      <td className="py-2.5">
                        <label className="flex items-center gap-2 text-sm text-slate-700">
                          <input
                            type="checkbox"
                            checked={t.publikasikan}
                            onChange={(e) =>
                              ubah.mutate({ id: t.id, publikasikan: e.target.checked })
                            }
                            className="size-4 rounded border-slate-300"
                          />
                          Tampilkan
                        </label>
                      </td>
                      <td className="py-2.5">
                        <AksiBaris
                          nama={`tahun anggaran ${t.tahun}`}
                          onHapus={() => hapus.mutate(t.id)}
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

// ---------------------------------------------------------------------------
// Tab: Kategori
// ---------------------------------------------------------------------------

function TabKategori() {
  const queryClient = useQueryClient()
  const kosong = { kelompok: 'Belanja', nama: '', kode: '', urutan_tampil: '' }
  const [form, setForm] = useState(kosong)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [sunting, setSunting] = useState<Kategori | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'apbdes', 'kategori'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Kategori[]>>('/admin/apbdes/kategori')
      return r.data.data
    },
  })

  const simpan = useMutation({
    mutationFn: () => {
      const isi = {
        kelompok: form.kelompok,
        nama: form.nama,
        kode: form.kode || null,
        urutan_tampil: form.urutan_tampil ? Number(form.urutan_tampil) : null,
      }

      return sunting
        ? api.put(`/admin/apbdes/kategori/${sunting.id}`, isi)
        : api.post('/admin/apbdes/kategori', isi)
    },
    onSuccess: () => {
      // Kelompok dipertahankan saat menambah beruntun: kategori lazim
      // dimasukkan sekelompok-sekelompok.
      setForm({ ...kosong, kelompok: sunting ? 'Belanja' : form.kelompok })
      setSunting(null)
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'apbdes', 'kategori'] })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapusKategori = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/apbdes/kategori/${id}`),
    onSuccess: () => {
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'apbdes', 'kategori'] })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menghapus.', 0)),
  })

  return (
    <div className="space-y-6">
      <Kartu
        judul={sunting ? `Ubah Kategori: ${sunting.nama}` : 'Tambah Kategori'}
        anak={
          <form
            onSubmit={(e) => {
              e.preventDefault()
              simpan.mutate()
            }}
            className="space-y-4"
          >
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}
            <div className="grid gap-4 sm:grid-cols-4">
              <Kolom label="Kelompok" htmlFor="kelompok" galat={galat?.fieldError('kelompok')}>
                <Pilihan
                  id="kelompok"
                  value={form.kelompok}
                  onChange={(v) => setForm({ ...form, kelompok: v })}
                  galat={galat?.fieldError('kelompok')}
                  options={KELOMPOK.map((k) => ({ value: k, label: k }))}
                />
              </Kolom>

              <div className="sm:col-span-2">
                <Kolom label="Nama Kategori" htmlFor="nama-kat" galat={galat?.fieldError('nama')}>
                  <Input
                    id="nama-kat"
                    required
                    placeholder="mis. Belanja Barang dan Jasa"
                    value={form.nama}
                    onChange={(e) => setForm({ ...form, nama: e.target.value })}
                    galat={galat?.fieldError('nama')}
                  />
                </Kolom>
              </div>

              <Kolom label="Kode (opsional)" htmlFor="kode-kat">
                <Input
                  id="kode-kat"
                  value={form.kode}
                  onChange={(e) => setForm({ ...form, kode: e.target.value })}
                />
              </Kolom>
            </div>
            <div className="flex flex-wrap gap-2">
              <Tombol type="submit" disabled={simpan.isPending}>
                {simpan.isPending ? 'Menyimpan…' : sunting ? 'Simpan Perubahan' : 'Tambah Kategori'}
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
            <p className="py-8 text-center text-slate-500">Belum ada kategori.</p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {data.map((k) => (
                <li key={k.id} className="flex items-center justify-between gap-4 py-3">
                  <div className="min-w-0">
                    <p className="truncate font-medium text-slate-900">{k.nama}</p>
                    <p className="text-sm text-slate-500">
                      {[k.kelompok, k.kode].filter(Boolean).join(' · ')}
                    </p>
                  </div>

                  <AksiBaris
                    nama={`kategori “${k.nama}”`}
                    onSunting={() => {
                      setSunting(k)
                      setForm({
                        kelompok: k.kelompok,
                        nama: k.nama,
                        kode: k.kode ?? '',
                        urutan_tampil:
                          k.urutan_tampil == null ? '' : String(k.urutan_tampil),
                      })
                      setGalat(null)
                      window.scrollTo({ top: 0, behavior: 'smooth' })
                    }}
                    onHapus={() => hapusKategori.mutate(k.id)}
                    sedangProses={hapusKategori.isPending}
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

// ---------------------------------------------------------------------------
// Tab: Rincian Item
// ---------------------------------------------------------------------------

const ITEM_KOSONG = {
  budget_category_id: '',
  nama_item: '',
  jumlah_anggaran: '',
  jumlah_realisasi: '',
  keterangan: '',
}

function TabItem() {
  const queryClient = useQueryClient()
  const [tahunId, setTahunId] = useState<string>('')
  const [form, setForm] = useState(ITEM_KOSONG)
  const [editId, setEditId] = useState<number | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const { data: tahun } = useQuery({
    queryKey: ['admin', 'apbdes', 'tahun'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<TahunAnggaran[]>>('/admin/apbdes/tahun')
      return r.data.data
    },
  })

  const { data: kategori } = useQuery({
    queryKey: ['admin', 'apbdes', 'kategori'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Kategori[]>>('/admin/apbdes/kategori')
      return r.data.data
    },
  })

  // Pilih tahun terbaru secara default begitu daftar tahun tersedia.
  const tahunTerpilih = tahunId || (tahun?.[0] ? String(tahun[0].id) : '')

  const { data: items, isPending } = useQuery({
    queryKey: ['admin', 'apbdes', 'items', tahunTerpilih],
    enabled: !!tahunTerpilih,
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Item[]>>('/admin/apbdes/items', {
        params: { budget_year_id: Number(tahunTerpilih) },
      })
      return r.data.data
    },
  })

  function reset() {
    setForm(ITEM_KOSONG)
    setEditId(null)
    setGalat(null)
  }

  const simpan = useMutation({
    mutationFn: () => {
      const payload = {
        budget_year_id: Number(tahunTerpilih),
        budget_category_id: Number(form.budget_category_id),
        nama_item: form.nama_item,
        jumlah_anggaran: Number(form.jumlah_anggaran),
        jumlah_realisasi: form.jumlah_realisasi === '' ? null : Number(form.jumlah_realisasi),
        keterangan: form.keterangan || null,
      }
      return editId
        ? api.put(`/admin/apbdes/items/${editId}`, payload)
        : api.post('/admin/apbdes/items', payload)
    },
    onSuccess: () => {
      reset()
      void queryClient.invalidateQueries({ queryKey: ['admin', 'apbdes', 'items'] })
      void queryClient.invalidateQueries({ queryKey: ['admin', 'apbdes', 'tahun'] })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/apbdes/items/${id}`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin', 'apbdes', 'items'] })
      void queryClient.invalidateQueries({ queryKey: ['admin', 'apbdes', 'tahun'] })
    },
  })

  function suntingItem(it: Item) {
    setEditId(it.id)
    setGalat(null)
    setForm({
      budget_category_id: String(it.budget_category_id),
      nama_item: it.nama_item,
      jumlah_anggaran: String(it.jumlah_anggaran),
      jumlah_realisasi: it.jumlah_realisasi === null ? '' : String(it.jumlah_realisasi),
      keterangan: it.keterangan ?? '',
    })
  }

  function kirim(e: FormEvent) {
    e.preventDefault()
    simpan.mutate()
  }

  if (!tahun?.length) {
    return (
      <Kartu
        anak={
          <p className="py-8 text-center text-slate-500">
            Belum ada tahun anggaran. Tambahkan lewat tab <strong>Tahun Anggaran</strong> dahulu.
          </p>
        }
      />
    )
  }

  return (
    <div className="space-y-6">
      <div className="max-w-xs">
        <Kolom label="Tahun Anggaran" htmlFor="pilih-tahun">
          <Pilihan
            id="pilih-tahun"
            value={tahunTerpilih}
            onChange={(v) => {
              setTahunId(v)
              reset()
            }}
            options={tahun.map((t) => ({ value: String(t.id), label: String(t.tahun) }))}
          />
        </Kolom>
      </div>

      <Kartu
        judul={editId ? 'Ubah Item' : 'Tambah Item'}
        anak={
          <form onSubmit={kirim} className="space-y-4">
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

            {!kategori?.length ? (
              <Pemberitahuan
                jenis="galat"
                pesan="Belum ada kategori. Tambahkan lewat tab Kategori dahulu."
              />
            ) : (
              <>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Kolom
                    label="Kategori"
                    htmlFor="budget_category_id"
                    galat={galat?.fieldError('budget_category_id')}
                  >
                    <Pilihan
                      id="budget_category_id"
                      value={form.budget_category_id}
                      onChange={(v) => setForm({ ...form, budget_category_id: v })}
                      placeholder="Pilih kategori…"
                      galat={galat?.fieldError('budget_category_id')}
                      options={kategori.map((k) => ({
                        value: String(k.id),
                        label: `${k.kelompok} — ${k.nama}`,
                      }))}
                    />
                  </Kolom>

                  <Kolom
                    label="Nama Item"
                    htmlFor="nama_item"
                    galat={galat?.fieldError('nama_item')}
                  >
                    <Input
                      id="nama_item"
                      required
                      value={form.nama_item}
                      onChange={(e) => setForm({ ...form, nama_item: e.target.value })}
                      galat={galat?.fieldError('nama_item')}
                    />
                  </Kolom>

                  <Kolom
                    label="Jumlah Anggaran (Rp)"
                    htmlFor="jumlah_anggaran"
                    galat={galat?.fieldError('jumlah_anggaran')}
                  >
                    <Input
                      id="jumlah_anggaran"
                      type="number"
                      min={0}
                      required
                      value={form.jumlah_anggaran}
                      onChange={(e) => setForm({ ...form, jumlah_anggaran: e.target.value })}
                      galat={galat?.fieldError('jumlah_anggaran')}
                    />
                  </Kolom>

                  <Kolom
                    label="Jumlah Realisasi (Rp)"
                    htmlFor="jumlah_realisasi"
                    petunjuk="Kosongkan bila belum ada realisasi."
                    galat={galat?.fieldError('jumlah_realisasi')}
                  >
                    <Input
                      id="jumlah_realisasi"
                      type="number"
                      min={0}
                      value={form.jumlah_realisasi}
                      onChange={(e) => setForm({ ...form, jumlah_realisasi: e.target.value })}
                      galat={galat?.fieldError('jumlah_realisasi')}
                    />
                  </Kolom>
                </div>

                <Kolom label="Keterangan" htmlFor="keterangan-item">
                  <TextArea
                    id="keterangan-item"
                    rows={2}
                    value={form.keterangan}
                    onChange={(e) => setForm({ ...form, keterangan: e.target.value })}
                  />
                </Kolom>

                <div className="flex gap-3">
                  <Tombol type="submit" disabled={simpan.isPending}>
                    {simpan.isPending ? 'Menyimpan…' : editId ? 'Simpan Perubahan' : 'Tambah Item'}
                  </Tombol>
                  {editId && (
                    <Tombol type="button" variasi="sekunder" onClick={reset}>
                      Batal
                    </Tombol>
                  )}
                </div>
              </>
            )}
          </form>
        }
      />

      <Kartu
        judul="Daftar Item"
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !items?.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada item pada tahun ini.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-left text-slate-500">
                    <th scope="col" className="pb-2 font-medium">Kategori</th>
                    <th scope="col" className="pb-2 font-medium">Item</th>
                    <th scope="col" className="pb-2 text-right font-medium">Anggaran</th>
                    <th scope="col" className="pb-2 text-right font-medium">Realisasi</th>
                    <th scope="col" className="pb-2 font-medium">
                      <span className="sr-only">Aksi</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((it) => (
                    <tr key={it.id} className="border-b border-slate-100">
                      <td className="py-2.5 pr-4 text-slate-600">
                        {it.kategori ? `${it.kategori.kelompok} — ${it.kategori.nama}` : '—'}
                      </td>
                      <td className="py-2.5 pr-4 font-medium text-slate-900">{it.nama_item}</td>
                      <td className="py-2.5 pr-4 text-right tabular-nums text-slate-700">
                        {formatRupiah(it.jumlah_anggaran)}
                      </td>
                      <td className="py-2.5 pr-4 text-right tabular-nums text-slate-700">
                        {it.jumlah_realisasi === null ? '—' : formatRupiah(it.jumlah_realisasi)}
                      </td>
                      <td className="py-2.5">
                        <AksiBaris
                          nama={`item “${it.nama_item}”`}
                          onSunting={() => suntingItem(it)}
                          onHapus={() => hapus.mutate(it.id)}
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

ApbdesAdminPage.layout = (page: ReactNode) => <LayoutAdmin judul="APBDes">{page}</LayoutAdmin>
