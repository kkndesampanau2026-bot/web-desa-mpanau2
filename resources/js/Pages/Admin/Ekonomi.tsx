import { Fragment, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, urlBerkas, type ApiSuccess } from '@/lib/api'
import { keFormData } from '@/lib/berkas'
import { Kartu, Kolom, Input, Pemberitahuan, TextArea, Tombol } from '@/Components/Admin/Form'
import { InputBerkas } from '@/Components/Admin/Berkas'
import { PengelolaFoto, type Foto } from '@/Components/Admin/PengelolaFoto'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import type { ReactNode } from 'react'

interface Potensi {
  id: number
  kategori: string
  judul: string
  slug: string
  deskripsi: string | null
  foto: string | null
  status_tampil: boolean
}

interface Wisata {
  id: number
  nama: string
  slug: string
  deskripsi: string | null
  harga_tiket: string | null
  status_tampil: boolean
  photos: Foto[]
  photos_count: number
}

interface Produk {
  id: number
  nama_produk: string
  slug: string
  kategori: string | null
  harga: string | number | null
  satuan: string | null
  tersedia: boolean
  nama_penjual: string
  kontak_wa: string | null
  status_tampil: boolean
  photos: Foto[]
  photos_count: number
}

const KATEGORI_POTENSI = [
  'Ekonomi',
  'Pariwisata',
  'Pertanian',
  'Industri Kreatif',
  'Lingkungan/Kelestarian',
]

const FORMAT_RUPIAH = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  maximumFractionDigits: 0,
})

/**
 * CMS Potensi Desa, Wisata, dan Produk UMKM — PRD 5.12–5.14.
 *
 * Modul Produk sengaja tidak memiliki pengelolaan pesanan: cakupannya
 * katalog saja (DEVIASI A4).
 */
export default function EkonomiAdminPage() {
  const [tab, setTab] = useState<'potensi' | 'wisata' | 'produk'>('potensi')

  return (
    <div className="max-w-4xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">Potensi &amp; Ekonomi Desa</h1>
        <p className="mt-1 text-sm text-slate-600">
          Kelola potensi desa, destinasi wisata, dan katalog produk UMKM yang tampil di
          situs publik.
        </p>
      </div>

      <div role="tablist" className="flex gap-1 border-b border-slate-200">
        {(
          [
            ['potensi', 'Potensi Desa'],
            ['wisata', 'Wisata'],
            ['produk', 'Produk UMKM'],
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

      {tab === 'potensi' && <PanelPotensi />}
      {tab === 'wisata' && <PanelWisata />}
      {tab === 'produk' && <PanelProduk />}
    </div>
  )
}

function PanelPotensi() {
  const queryClient = useQueryClient()
  const [form, setForm] = useState({ kategori: KATEGORI_POTENSI[0], judul: '', deskripsi: '' })
  const [foto, setFoto] = useState<File | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'potensi'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Potensi[]>>('/admin/potensi')
      return r.data.data
    },
  })

  const tambah = useMutation({
    mutationFn: () => api.post('/admin/potensi', keFormData({ ...form, foto })),
    onSuccess: () => {
      setForm({ kategori: form.kategori, judul: '', deskripsi: '' })
      setFoto(null)
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'potensi'] })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/potensi/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'potensi'] }),
  })

  return (
    <div className="space-y-6">
      <Kartu
        judul="Tambah Potensi Desa"
        anak={
          <form
            onSubmit={(e: FormEvent) => {
              e.preventDefault()
              tambah.mutate()
            }}
            className="space-y-4"
          >
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

            <div className="grid gap-4 sm:grid-cols-2">
              <Kolom label="Kategori" htmlFor="kategori-potensi">
                <select
                  id="kategori-potensi"
                  value={form.kategori}
                  onChange={(e) => setForm({ ...form, kategori: e.target.value })}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-900"
                >
                  {KATEGORI_POTENSI.map((k) => (
                    <option key={k} value={k}>
                      {k}
                    </option>
                  ))}
                </select>
              </Kolom>

              <Kolom label="Judul" htmlFor="judul-potensi" galat={galat?.fieldError('judul')}>
                <Input
                  id="judul-potensi"
                  required
                  value={form.judul}
                  onChange={(e) => setForm({ ...form, judul: e.target.value })}
                  galat={galat?.fieldError('judul')}
                />
              </Kolom>
            </div>

            <Kolom label="Deskripsi" htmlFor="deskripsi-potensi">
              <TextArea
                id="deskripsi-potensi"
                rows={3}
                value={form.deskripsi}
                onChange={(e) => setForm({ ...form, deskripsi: e.target.value })}
              />
            </Kolom>

            <InputBerkas
              label="Foto Potensi"
              jenis="gambar"
              berkas={foto}
              onPilih={setFoto}
              galat={galat?.fieldError('foto')}
            />

            <Tombol type="submit" disabled={tambah.isPending}>
              {tambah.isPending ? 'Menyimpan…' : 'Tambah Potensi'}
            </Tombol>
          </form>
        }
      />

      <Kartu
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada potensi desa.</p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {data.map((p) => (
                <li key={p.id} className="flex items-center justify-between gap-4 py-3">
                  {p.foto && (
                    <img
                      src={urlBerkas(p.foto) ?? undefined}
                      alt=""
                      className="h-11 w-11 shrink-0 rounded-lg border border-slate-200 object-cover"
                    />
                  )}
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-medium text-slate-900">{p.judul}</p>
                    <p className="truncate text-sm text-slate-500">
                      {p.kategori}
                      {!p.status_tampil && ' · disembunyikan'}
                    </p>
                  </div>
                  <button
                    onClick={() => {
                      if (confirm(`Hapus potensi "${p.judul}"?`)) hapus.mutate(p.id)
                    }}
                    className="shrink-0 text-sm text-red-600 hover:underline"
                  >
                    Hapus
                  </button>
                </li>
              ))}
            </ul>
          )
        }
      />
    </div>
  )
}

function PanelWisata() {
  const queryClient = useQueryClient()
  const [form, setForm] = useState({ nama: '', deskripsi: '', harga_tiket: '', alamat: '' })
  const [fotoDibuka, setFotoDibuka] = useState<number | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'wisata'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Wisata[]>>('/admin/wisata')
      return r.data.data
    },
  })

  const tambah = useMutation({
    mutationFn: () => api.post('/admin/wisata', form),
    onSuccess: () => {
      setForm({ nama: '', deskripsi: '', harga_tiket: '', alamat: '' })
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'wisata'] })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/wisata/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'wisata'] }),
  })

  return (
    <div className="space-y-6">
      <Kartu
        judul="Tambah Destinasi Wisata"
        anak={
          <form
            onSubmit={(e: FormEvent) => {
              e.preventDefault()
              tambah.mutate()
            }}
            className="space-y-4"
          >
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

            <div className="grid gap-4 sm:grid-cols-2">
              <Kolom label="Nama Destinasi" htmlFor="nama-wisata" galat={galat?.fieldError('nama')}>
                <Input
                  id="nama-wisata"
                  required
                  value={form.nama}
                  onChange={(e) => setForm({ ...form, nama: e.target.value })}
                  galat={galat?.fieldError('nama')}
                />
              </Kolom>

              <Kolom
                label="Harga Tiket"
                htmlFor="harga-tiket"
                petunjuk='Boleh berupa teks, misalnya "Gratis" atau "Sukarela".'
                galat={galat?.fieldError('harga_tiket')}
              >
                <Input
                  id="harga-tiket"
                  value={form.harga_tiket}
                  onChange={(e) => setForm({ ...form, harga_tiket: e.target.value })}
                  galat={galat?.fieldError('harga_tiket')}
                />
              </Kolom>
            </div>

            <Kolom label="Alamat" htmlFor="alamat-wisata">
              <Input
                id="alamat-wisata"
                value={form.alamat}
                onChange={(e) => setForm({ ...form, alamat: e.target.value })}
              />
            </Kolom>

            <Kolom label="Deskripsi" htmlFor="deskripsi-wisata">
              <TextArea
                id="deskripsi-wisata"
                rows={3}
                value={form.deskripsi}
                onChange={(e) => setForm({ ...form, deskripsi: e.target.value })}
              />
            </Kolom>

            <Tombol type="submit" disabled={tambah.isPending}>
              {tambah.isPending ? 'Menyimpan…' : 'Tambah Destinasi'}
            </Tombol>
          </form>
        }
      />

      <Kartu
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada destinasi wisata.</p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {data.map((w) => (
                <li key={w.id} className="py-3">
                  <div className="flex items-center justify-between gap-4">
                    <div className="min-w-0">
                      <p className="truncate font-medium text-slate-900">{w.nama}</p>
                      <p className="truncate text-sm text-slate-500">
                        {[
                          w.harga_tiket && `Tiket: ${w.harga_tiket}`,
                          `${w.photos_count} foto`,
                          !w.status_tampil && 'disembunyikan',
                        ]
                          .filter(Boolean)
                          .join(' · ')}
                      </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-3 text-sm">
                      <button
                        onClick={() => setFotoDibuka(fotoDibuka === w.id ? null : w.id)}
                        aria-expanded={fotoDibuka === w.id}
                        className="text-slate-700 hover:underline"
                      >
                        {fotoDibuka === w.id ? 'Tutup Foto' : 'Kelola Foto'}
                      </button>
                      <button
                        onClick={() => {
                          if (confirm(`Hapus destinasi "${w.nama}"?`)) hapus.mutate(w.id)
                        }}
                        className="text-red-600 hover:underline"
                      >
                        Hapus
                      </button>
                    </div>
                  </div>

                  {fotoDibuka === w.id && (
                    <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                      <PengelolaFoto
                        urlUnggah={`/admin/wisata/${w.id}/foto`}
                        urlHapus={(idFoto) => `/admin/wisata/${w.id}/foto/${idFoto}`}
                        foto={w.photos ?? []}
                        onBerubah={() =>
                          void queryClient.invalidateQueries({ queryKey: ['admin', 'wisata'] })
                        }
                        petunjuk="Foto pertama dipakai sebagai gambar utama destinasi."
                      />
                    </div>
                  )}
                </li>
              ))}
            </ul>
          )
        }
      />
    </div>
  )
}

function PanelProduk() {
  const queryClient = useQueryClient()
  const [form, setForm] = useState({
    nama_produk: '',
    kategori: '',
    harga: '',
    satuan: '',
    nama_penjual: '',
    kontak_wa: '',
  })
  const [fotoDibuka, setFotoDibuka] = useState<number | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'produk'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Produk[]>>('/admin/produk')
      return r.data.data
    },
  })

  const tambah = useMutation({
    mutationFn: () => api.post('/admin/produk', { ...form, harga: form.harga || null }),
    onSuccess: () => {
      setForm({
        nama_produk: '',
        kategori: '',
        harga: '',
        satuan: '',
        nama_penjual: '',
        kontak_wa: '',
      })
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'produk'] })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/produk/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'produk'] }),
  })

  return (
    <div className="space-y-6">
      <Kartu
        judul="Tambah Produk UMKM"
        anak={
          <form
            onSubmit={(e: FormEvent) => {
              e.preventDefault()
              tambah.mutate()
            }}
            className="space-y-4"
          >
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

            <div className="grid gap-4 sm:grid-cols-2">
              <Kolom
                label="Nama Produk"
                htmlFor="nama-produk"
                galat={galat?.fieldError('nama_produk')}
              >
                <Input
                  id="nama-produk"
                  required
                  value={form.nama_produk}
                  onChange={(e) => setForm({ ...form, nama_produk: e.target.value })}
                  galat={galat?.fieldError('nama_produk')}
                />
              </Kolom>

              <Kolom label="Kategori" htmlFor="kategori-produk">
                <Input
                  id="kategori-produk"
                  placeholder="mis. Makanan Ringan"
                  value={form.kategori}
                  onChange={(e) => setForm({ ...form, kategori: e.target.value })}
                />
              </Kolom>

              <Kolom label="Harga (Rp)" htmlFor="harga-produk" galat={galat?.fieldError('harga')}>
                <Input
                  id="harga-produk"
                  type="number"
                  min={0}
                  value={form.harga}
                  onChange={(e) => setForm({ ...form, harga: e.target.value })}
                  galat={galat?.fieldError('harga')}
                />
              </Kolom>

              <Kolom label="Satuan" htmlFor="satuan-produk">
                <Input
                  id="satuan-produk"
                  placeholder="mis. bungkus, kg, porsi"
                  value={form.satuan}
                  onChange={(e) => setForm({ ...form, satuan: e.target.value })}
                />
              </Kolom>

              <Kolom
                label="Nama Penjual"
                htmlFor="nama-penjual"
                galat={galat?.fieldError('nama_penjual')}
              >
                <Input
                  id="nama-penjual"
                  required
                  value={form.nama_penjual}
                  onChange={(e) => setForm({ ...form, nama_penjual: e.target.value })}
                  galat={galat?.fieldError('nama_penjual')}
                />
              </Kolom>

              <Kolom
                label="WhatsApp Penjual"
                htmlFor="kontak-wa"
                petunjuk="Pastikan pemilik usaha setuju nomornya ditampilkan ke publik."
                galat={galat?.fieldError('kontak_wa')}
              >
                <Input
                  id="kontak-wa"
                  placeholder="08xx atau +62xx"
                  value={form.kontak_wa}
                  onChange={(e) => setForm({ ...form, kontak_wa: e.target.value })}
                  galat={galat?.fieldError('kontak_wa')}
                />
              </Kolom>
            </div>

            <Tombol type="submit" disabled={tambah.isPending}>
              {tambah.isPending ? 'Menyimpan…' : 'Tambah Produk'}
            </Tombol>
          </form>
        }
      />

      <Kartu
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada produk UMKM.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-left text-slate-500">
                    <th scope="col" className="pb-2 font-medium">Produk</th>
                    <th scope="col" className="pb-2 font-medium">Harga</th>
                    <th scope="col" className="pb-2 font-medium">Penjual</th>
                    <th scope="col" className="pb-2 font-medium">
                      <span className="sr-only">Aksi</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {data.map((p) => (
                    <Fragment key={p.id}>
                      <tr className="border-b border-slate-100">
                        <td className="py-2.5 pr-4">
                          <div className="flex items-center gap-3">
                            {p.photos?.[0] ? (
                              <img
                                src={urlBerkas(p.photos[0].path) ?? undefined}
                                alt=""
                                className="h-10 w-10 shrink-0 rounded border border-slate-200 object-cover"
                              />
                            ) : (
                              <span
                                aria-hidden="true"
                                className="h-10 w-10 shrink-0 rounded border border-dashed border-slate-300"
                              />
                            )}
                            <div className="min-w-0">
                              <p className="font-medium text-slate-900">{p.nama_produk}</p>
                              <p className="text-xs text-slate-500">
                                {[
                                  p.kategori,
                                  `${p.photos_count} foto`,
                                  !p.tersedia && 'stok habis',
                                  !p.status_tampil && 'disembunyikan',
                                ]
                                  .filter(Boolean)
                                  .join(' · ')}
                              </p>
                            </div>
                          </div>
                        </td>
                        <td className="py-2.5 pr-4 tabular-nums text-slate-700">
                          {p.harga === null ? '—' : FORMAT_RUPIAH.format(Number(p.harga))}
                          {p.satuan && <span className="text-xs text-slate-500"> / {p.satuan}</span>}
                        </td>
                        <td className="py-2.5 pr-4 text-slate-700">
                          {p.nama_penjual}
                          {p.kontak_wa && (
                            <span className="block text-xs text-slate-500">{p.kontak_wa}</span>
                          )}
                        </td>
                        <td className="py-2.5 text-right whitespace-nowrap">
                          <button
                            onClick={() => setFotoDibuka(fotoDibuka === p.id ? null : p.id)}
                            aria-expanded={fotoDibuka === p.id}
                            className="mr-3 text-slate-700 hover:underline"
                          >
                            {fotoDibuka === p.id ? 'Tutup Foto' : 'Kelola Foto'}
                          </button>
                          <button
                            onClick={() => {
                              if (confirm(`Hapus produk "${p.nama_produk}"?`)) hapus.mutate(p.id)
                            }}
                            className="text-red-600 hover:underline"
                          >
                            Hapus
                          </button>
                        </td>
                      </tr>

                      {fotoDibuka === p.id && (
                        <tr key={`${p.id}-foto`} className="border-b border-slate-100">
                          {/* colSpan menyamai jumlah kolom tabel; tanpa ini baris
                              foto hanya selebar kolom pertama. */}
                          <td colSpan={4} className="bg-slate-50 p-4">
                            <PengelolaFoto
                              urlUnggah={`/admin/produk/${p.id}/foto`}
                              urlHapus={(idFoto) => `/admin/produk/${p.id}/foto/${idFoto}`}
                              foto={p.photos ?? []}
                              onBerubah={() =>
                                void queryClient.invalidateQueries({ queryKey: ['admin', 'produk'] })
                              }
                              petunjuk="Foto pertama dipakai sebagai gambar utama produk."
                            />
                          </td>
                        </tr>
                      )}
                    </Fragment>
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

EkonomiAdminPage.layout = (page: ReactNode) => <LayoutAdmin judul="Potensi & Ekonomi">{page}</LayoutAdmin>
